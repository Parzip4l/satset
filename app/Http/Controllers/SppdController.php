<?php

namespace App\Http\Controllers;

use App\Models\Master\Approval;
use App\Models\Master\Ticket;
use App\Services\SppdPdf;
use App\Services\SppdWorkflow;
use App\Support\ServiceDomains;
use App\Support\Sppd;
use App\Support\TicketAccess;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SppdController extends Controller
{
    private const COST_AMOUNT = 'regex:/^[0-9]{1,10}(\.[0-9]{1,2})?$/D';

    public function __construct(private readonly SppdWorkflow $workflow) {}

    public function index(Request $request)
    {
        $requests = Ticket::where('payload->request_type', Sppd::TYPE)
            ->where('requester_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return view('sppd.index', ['requests' => $requests, 'acceptingRequests' => ServiceDomains::sppdActive()]);
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

    public function store(Request $request)
    {
        $data = $this->formData($request);
        $this->requirePreview($request, $data);
        $ticket = $this->workflow->submit($request->user(), $data);

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
        $this->requirePreview($request, $data, $ticket);
        $ticket = $this->workflow->resubmit($ticket, $request->user(), $data);

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

        $bytes = $pdf->preview($this->workflow->preview($request->user(), $data));

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pratinjau-sppd.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Sppd-Preview-Token' => Crypt::encryptString($this->previewFingerprint($request, $data, $ticket)),
        ]);
    }

    private function requirePreview(Request $request, array $data, ?Ticket $ticket = null): void
    {
        $token = $request->input('preview_token');
        if (is_string($token) && strlen($token) <= 512) {
            try {
                if (hash_equals($this->previewFingerprint($request, $data, $ticket), Crypt::decryptString($token))) {
                    return;
                }
            } catch (DecryptException) {
                // Invalid tokens use the same prompt as missing or out-of-date previews.
            }
        }

        throw ValidationException::withMessages(['preview_token' => 'Buka pratinjau PDF dari isian terbaru sebelum mengirim SPPD.']);
    }

    private function previewFingerprint(Request $request, array $data, ?Ticket $ticket): string
    {
        return hash('sha256', serialize([$request->user()->id, $ticket?->id, $data]));
    }

    private function formData(Request $request, ?Ticket $ticket = null): array
    {
        return $request->validate($this->rules() + ($ticket
            ? ['version' => ['required', 'integer', 'min:1']]
            : ['submission_token' => ['required', 'uuid']]));
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
            'costs' => ['nullable', 'array:'.implode(',', array_keys(Sppd::COSTS))],
            'other_costs' => ['nullable', 'array'],
            'other_costs.*' => ['required', 'array:label,amount,note'],
            'other_costs.*.label' => ['required', 'string', 'max:120'],
            'other_costs.*.amount' => ['required', self::COST_AMOUNT],
            'other_costs.*.note' => ['nullable', 'string', 'max:90'],
            'notes' => ['nullable', 'string', 'max:500'],
            'bank' => ['nullable', 'array:name,account,holder'],
            'bank.name' => ['nullable', 'required_with:bank.account,bank.holder', 'string', 'max:50'],
            'bank.account' => ['nullable', 'required_with:bank.name,bank.holder', 'regex:/^[0-9]{5,30}$/D'],
            'bank.holder' => ['nullable', 'required_with:bank.name,bank.account', 'string', 'max:100'],
        ];
        foreach (array_keys(Sppd::COSTS) as $row) {
            $rules["costs.{$row}"] = ['nullable', 'array:amount,note'];
            $rules["costs.{$row}.amount"] = ['nullable', self::COST_AMOUNT];
            $rules["costs.{$row}.note"] = ['nullable', 'string', 'max:90'];
        }

        return $rules;
    }
}
