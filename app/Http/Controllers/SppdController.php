<?php

namespace App\Http\Controllers;

use App\Models\Master\Approval;
use App\Models\Master\Ticket;
use App\Services\SppdExchangeRate;
use App\Services\SppdPdf;
use App\Services\SppdWorkflow;
use App\Support\ServiceDomains;
use App\Support\Sppd;
use App\Support\SppdAccess;
use App\Support\TicketAccess;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SppdController extends Controller
{
    public function __construct(
        private readonly SppdWorkflow $workflow,
        private readonly SppdExchangeRate $exchangeRates,
    ) {}

    public function index(Request $request)
    {
        $isOperator = SppdAccess::canConfigure($request->user());
        $requests = Ticket::where('payload->request_type', Sppd::TYPE)
            ->when(! $isOperator, fn ($query) => $query->where('requester_id', $request->user()->id))
            ->latest()
            ->paginate(15);

        return view('sppd.index', [
            'requests' => $requests,
            'acceptingRequests' => ServiceDomains::sppdActive(),
            'isOperator' => $isOperator,
        ]);
    }

    public function create(Request $request)
    {
        $ticket = null;
        $sppd = [];
        $profile = $this->workflow->profile($request->user());
        $submissionToken = (string) Str::uuid();

        return view('sppd.form', compact('ticket', 'sppd', 'profile', 'submissionToken'));
    }

    public function requesterProfile(Request $request)
    {
        return response()->json($this->workflow->formProfile($request->user()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function exchangeRate()
    {
        return response()->json($this->exchangeRates->latest())
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $data = $this->formData($request);
        $ticket = $this->workflow->submit($request->user(), $data + $this->requirePreview($request, $data));

        return redirect()->route('sppd.show', $ticket)->with('success', 'SPPD berhasil diajukan.');
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->authorizeView($request, $ticket);
        $sppd = $ticket->payload['sppd'];
        $approvals = $ticket->approvals()->with('approver')->get()->keyBy('id');
        $history = $ticket->histories()->with('user')->latest('id')->get();

        return view('sppd.show', compact('ticket', 'sppd', 'approvals', 'history'));
    }

    public function edit(Request $request, Ticket $ticket)
    {
        $this->authorizeRevision($request, $ticket);
        $sppd = $ticket->payload['sppd'];
        $profile = $this->workflow->profile($request->user());
        $submissionToken = null;

        return view('sppd.form', compact('ticket', 'sppd', 'profile', 'submissionToken'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $this->authorizeRevision($request, $ticket);
        $data = $this->formData($request, $ticket);
        $ticket = $this->workflow->resubmit($ticket, $request->user(), $data + $this->requirePreview($request, $data, $ticket));

        return redirect()->route('sppd.show', $ticket)->with('success', 'Revisi SPPD berhasil diajukan.');
    }

    public function preview(Request $request, SppdPdf $pdf)
    {
        return $this->previewPdf($request, $pdf);
    }

    public function previewRevision(Request $request, Ticket $ticket, SppdPdf $pdf)
    {
        $this->authorizeRevision($request, $ticket);

        return $this->previewPdf($request, $pdf, $ticket);
    }

    public function decide(Request $request, Ticket $ticket, Approval $approval)
    {
        $this->authorizeView($request, $ticket);
        abort_unless((int) $approval->request_id === (int) $ticket->id, 404);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'revision_requested', 'rejected'])],
            'note' => ['nullable', 'required_if:decision,revision_requested,rejected', 'string', 'max:1000'],
        ]);
        $this->workflow->decide($ticket, $approval, $request->user(), $data['decision'], $data['note'] ?? null);

        return redirect()->route('sppd.show', $ticket)->with('success', 'Keputusan berhasil dicatat.');
    }

    public function proposeBudget(Request $request, Ticket $ticket)
    {
        $this->authorizeView($request, $ticket);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'costs' => ['sometimes', 'array'],
            'costs.*' => ['required', Sppd::COST_AMOUNT],
            'other_costs' => ['sometimes', 'array'],
            'other_costs.*' => ['required', Sppd::COST_AMOUNT],
        ]);
        $this->workflow->proposeBudget($ticket, $request->user(), $data);

        return redirect()->route('sppd.show', $ticket)->with('success', 'Usulan nominal dikirim ke pemohon untuk dikonfirmasi.');
    }

    public function attachment(Request $request, Ticket $ticket, string $kind)
    {
        $this->authorizeView($request, $ticket);
        abort_unless(isset(Sppd::ATTACHMENTS[$kind]), 404);
        $attachment = $ticket->attachments()->where('attachment_type', 'sppd_'.$kind)
            ->findOrFail(data_get($ticket->payload, "sppd.attachments.{$kind}.id") ?? 0);
        $disk = Storage::disk('sppd_private');
        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->download($attachment->file_path, $attachment->file_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(Request $request, Ticket $ticket, SppdPdf $pdf)
    {
        $this->authorizeView($request, $ticket);
        abort_unless(data_get($ticket->payload, 'workflow_status') === 'ISSUED', 404);

        $bytes = $pdf->make($ticket);
        $filename = str_replace('/', '-', Sppd::reference($ticket)).'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeView(Request $request, Ticket $ticket): void
    {
        abort_unless(Sppd::is($ticket), 404);
        abort_unless(TicketAccess::canView($request->user(), $ticket), 403);
    }

    private function authorizeRevision(Request $request, Ticket $ticket): void
    {
        $this->authorizeView($request, $ticket);
        abort_unless((int) $ticket->requester_id === (int) $request->user()->id, 403);
        abort_unless(data_get($ticket->payload, 'workflow_status') === 'REVISION_REQUESTED', 409);
    }

    private function previewPdf(Request $request, SppdPdf $pdf, ?Ticket $ticket = null)
    {
        $data = $this->formData($request, $ticket);
        abort_if($ticket && (int) $data['version'] !== (int) data_get($ticket->payload, 'sppd.version'), 409, 'Pengajuan sudah berubah. Muat ulang halaman SPPD.');

        $quote = $this->usesUsd($data) ? $this->exchangeRates->latest() : null;
        $document = $this->workflow->preview($request->user(), $data + ['exchange_rate' => $quote]);
        $bytes = $pdf->preview($document);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pratinjau-sppd.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Sppd-Preview-Token' => Crypt::encryptString(json_encode([
                'fingerprint' => $this->previewFingerprint($request, $data, $ticket),
                'exchange_rate' => $quote,
                'preview_job_level' => $document['profile']['job_level'],
            ], JSON_THROW_ON_ERROR)),
        ]);
    }

    private function requirePreview(Request $request, array $data, ?Ticket $ticket = null): array
    {
        $token = $request->input('preview_token');
        if (is_string($token) && strlen($token) <= 2048) {
            try {
                $preview = json_decode(Crypt::decryptString($token), true);
                if (is_string($preview['fingerprint'] ?? null) && hash_equals($this->previewFingerprint($request, $data, $ticket), $preview['fingerprint'])) {
                    return ['exchange_rate' => $preview['exchange_rate'], 'preview_job_level' => $preview['preview_job_level']];
                }
            } catch (DecryptException) {
                // Invalid tokens use the same prompt as missing or out-of-date previews.
            }
        }

        throw ValidationException::withMessages(['preview_token' => 'Buka pratinjau PDF dari isian terbaru sebelum mengirim SPPD.']);
    }

    private function previewFingerprint(Request $request, array $data, ?Ticket $ticket): string
    {
        unset($data['uploads']);

        return hash('sha256', serialize([$request->user()->id, $ticket?->id, $data]));
    }

    private function formData(Request $request, ?Ticket $ticket = null): array
    {
        $data = $request->validate($this->rules() + ($ticket
            ? ['version' => ['required', 'integer', 'min:1']]
            : ['submission_token' => ['required', 'uuid']]));
        $manifest = data_get($ticket?->payload, 'sppd.attachments', []);
        $forecastMatchesTrip = ! $ticket || (
            $data['depart_on'] === data_get($ticket->payload, 'sppd.depart_on')
            && $data['return_on'] === data_get($ticket->payload, 'sppd.return_on')
            && $data['destination'] === data_get($ticket->payload, 'sppd.destination')
        );
        if ($data['category'] !== 'international' || ! $forecastMatchesTrip) {
            unset($manifest['forecast']);
        }
        foreach ($data['uploads'] ?? [] as $key => $file) {
            if ($file) {
                $name = trim(str_replace(["\r", "\n", "\0", '/', '\\'], '_', $file->getClientOriginalName()));
                $manifest[$key] = [
                    'name' => Str::limit($name, 240, ''),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                ];
            }
        }
        $data['uploads'] = array_filter($data['uploads'] ?? []);
        $data['attachment_manifest'] = $manifest;

        foreach (['costs', 'other_costs'] as $group) {
            foreach ($data[$group] ?? [] as $key => $row) {
                if ((float) ($row['amount'] ?? 0) > 0 && (float) ($row['usd_amount'] ?? 0) > 0) {
                    throw ValidationException::withMessages(["{$group}.{$key}.usd_amount" => 'Isi biaya dalam rupiah atau USD untuk setiap pengeluaran.']);
                }
            }
        }

        $temperature = $data['forecast_temperature'] ?? null;
        $winter = $data['costs'][Sppd::WINTER_COST] ?? [];
        if (max((float) ($winter['amount'] ?? 0), (float) ($winter['usd_amount'] ?? 0)) > 0
            && ($temperature === null || (float) $temperature >= 15)) {
            throw ValidationException::withMessages(['forecast_temperature' => 'Biaya pakaian musim dingin berlaku untuk prakiraan di bawah 15°C.']);
        }
        if ($temperature !== null && (float) $temperature < 15 && empty($manifest['forecast'])) {
            throw ValidationException::withMessages(['uploads.forecast' => 'Lampirkan screenshot prakiraan cuaca untuk tanggal perjalanan dinas.']);
        }

        return $data;
    }

    private function usesUsd(array $data): bool
    {
        foreach (array_merge($data['costs'] ?? [], $data['other_costs'] ?? []) as $row) {
            if ((float) ($row['usd_amount'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function rules(): array
    {
        $rules = [
            'nik' => ['nullable', 'string', 'max:40'],
            'position' => ['nullable', 'string', 'max:100'],
            'division' => ['nullable', 'string', 'max:100'],
            'directorate' => ['nullable', 'string', 'max:100'],
            'category' => ['required', Rule::in(array_keys(Sppd::CATEGORIES))],
            'origin' => ['required', 'string', 'max:120'],
            'destination' => ['required', 'string', 'max:120'],
            'depart_on' => ['required', 'date_format:Y-m-d'],
            'return_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:depart_on'],
            'purpose' => ['required', 'string', 'max:700'],
            'international_purpose' => ['exclude_unless:category,international', 'required', Rule::in(array_keys(Sppd::INTERNATIONAL_PURPOSES))],
            'forecast_temperature' => ['exclude_unless:category,international', 'nullable', 'numeric', 'between:-100,100'],
            'uploads' => ['nullable', 'array:invitation,assignment,forecast'],
            'uploads.invitation' => ['nullable', 'file', 'mimes:pdf,jpeg,png,webp', 'max:5120'],
            'uploads.assignment' => ['nullable', 'file', 'mimes:pdf,jpeg,png,webp', 'max:5120'],
            'uploads.forecast' => ['exclude_unless:category,international', 'nullable', 'file', 'mimes:jpeg,png,webp', 'max:5120'],
            'costs' => ['nullable', 'array:'.implode(',', array_keys(Sppd::costRows(true)))],
            'other_costs' => ['nullable', 'array', 'max:40'],
            'other_costs.*' => ['required', 'array:label,amount,usd_amount,note'],
            'other_costs.*.label' => ['required', 'string', 'max:120'],
            'other_costs.*.amount' => ['nullable', 'required_without:other_costs.*.usd_amount', Sppd::COST_AMOUNT],
            'other_costs.*.usd_amount' => ['prohibited_unless:category,international', 'nullable', 'regex:/^[0-9]{1,6}(\.[0-9]{1,2})?$/D'],
            'other_costs.*.note' => ['nullable', 'string', 'max:90'],
            'notes' => ['nullable', 'string', 'max:500'],
            'bank' => ['nullable', 'array:name,account,holder'],
            'bank.name' => ['nullable', 'required_with:bank.account,bank.holder', 'string', 'max:50'],
            'bank.account' => ['nullable', 'required_with:bank.name,bank.holder', 'regex:/^[0-9]{5,30}$/D'],
            'bank.holder' => ['nullable', 'required_with:bank.name,bank.account', 'string', 'max:100'],
        ];
        foreach (array_keys(Sppd::costRows(true)) as $row) {
            $supportsProvider = in_array($row, Sppd::PROVIDER_ROWS, true);
            $rules["costs.{$row}"] = ['nullable', 'array:amount,usd_amount,note'.($supportsProvider ? ',provider' : '')];
            if ($row === Sppd::WINTER_COST) {
                array_unshift($rules["costs.{$row}"], 'exclude_unless:category,international');
            }
            $rules["costs.{$row}.amount"] = ['nullable', Sppd::COST_AMOUNT];
            $rules["costs.{$row}.usd_amount"] = ['prohibited_unless:category,international', 'nullable', 'regex:/^[0-9]{1,6}(\.[0-9]{1,2})?$/D'];
            $rules["costs.{$row}.note"] = ['nullable', 'string', 'max:90'];
            if ($supportsProvider) {
                $rules["costs.{$row}.provider"] = ['nullable', Rule::in(array_keys(Sppd::PROVIDERS))];
            }
        }

        return $rules;
    }
}
