<?php

namespace App\Services;

use App\Models\Master\Ticket;
use App\Support\Sppd;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SppdPdf
{
    public function make(Ticket $ticket): string
    {
        $sppd = $ticket->payload['sppd'];
        $approvals = $ticket->approvals()->with('approver')->whereIn('id', $sppd['approvals'])->get()->keyBy('id');
        $signatures = [];
        foreach (Sppd::STAGES as $level => [$action, $role]) {
            $approval = $approvals->get($sppd['approvals'][$level] ?? null);
            $signature = $level === 0 ? $sppd['requester_signature'] : $approval?->getAttributes();
            $url = data_get($signature, 'portal_signature_url');

            abort_unless($url && ($level === 0 || $approval?->status === 'approved'), 409, 'Tanda tangan Portal untuk SPPD belum lengkap.');

            $qr = QrCode::format('svg')->size(88)->margin(4)->errorCorrection('M')->generate($url);
            $portal = $level === 0 ? ($signature['portal_qr_payload'] ?? []) : $approval->portal_qr_payload;
            $signatures[] = [
                'action' => $action,
                'role' => $role,
                'qr' => 'data:image/svg+xml;base64,'.base64_encode((string) $qr),
                'url' => $url,
                'name' => data_get($portal, 'signer.name') ?: ($level === 0 ? $sppd['profile']['name'] : $approval->approver?->name),
                'position' => data_get($portal, 'signer.position') ?: ($level === 0 ? $sppd['profile']['position'] : $role),
                'date' => CarbonImmutable::parse($level === 0 ? $sppd['submitted_at'] : $approval->decided_at)->timezone('Asia/Jakarta')->format('d/m/Y'),
            ];
        }

        return Pdf::loadView('sppd.pdf', [
            'sppd' => $sppd,
            'reference' => Sppd::reference($ticket),
            'logo' => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('logo-lrtj.png'))),
            'signatures' => $signatures,
        ])->setPaper('a4', 'portrait')->output();
    }
}
