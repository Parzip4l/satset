<?php

namespace App\Services;

use App\Models\Master\Attachment;
use App\Models\Master\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class GaRequestFindingReportService
{
    public function make(Ticket $ticket): string
    {
        $ticket->loadMissing(['requester', 'status', 'attachments', 'histories.user']);

        $evidenceAttachments = $ticket->attachments
            ->filter(fn (Attachment $attachment) => in_array($attachment->attachment_type, [
                'ga_report_evidence',
                'ga_follow_up_evidence',
            ], true));

        $imageEvidence = $evidenceAttachments
            ->filter(fn (Attachment $attachment) => str_starts_with((string) $attachment->mime_type, 'image/'))
            ->map(fn (Attachment $attachment) => [
                'attachment' => $attachment,
                'data_uri' => $this->attachmentDataUri($attachment),
            ])
            ->filter(fn (array $item) => filled($item['data_uri']))
            ->values();

        $pdfEvidence = $evidenceAttachments
            ->filter(fn (Attachment $attachment) => $attachment->mime_type === 'application/pdf'
                || strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION)) === 'pdf')
            ->filter(fn (Attachment $attachment) => Storage::disk('public')->exists($attachment->file_path))
            ->values();

        $reportPdf = Pdf::loadView('ticket.ga-request-finding-report', [
            'ticket' => $ticket,
            'payload' => $ticket->payload ?? [],
            'followUps' => collect(data_get($ticket->payload, 'ga_follow_ups', [])),
            'imageEvidence' => $imageEvidence,
            'pdfEvidence' => $pdfEvidence,
            'logoDataUri' => $this->localFileDataUri(public_path('logo-lrtj.png'), 'image/png'),
            'esignLogoDataUri' => $this->localFileDataUri(public_path('assets/images/logo-esign.png'), 'image/png'),
            'requesterSignatureQr' => $this->signatureQrDataUri(data_get($ticket->payload, 'portal_signatures.requester', [])),
            'gaOfficerSignatureQr' => $this->signatureQrDataUri(data_get($ticket->payload, 'portal_signatures.ga_officer', [])),
        ])->setPaper('a4')->output();

        return $pdfEvidence->isEmpty()
            ? $reportPdf
            : $this->appendPdfEvidence($reportPdf, $pdfEvidence, $ticket);
    }

    private function appendPdfEvidence(string $reportPdf, $pdfEvidence, Ticket $ticket): string
    {
        $merged = new Fpdi;
        $this->appendPdfBytes($merged, $reportPdf);

        foreach ($pdfEvidence as $attachment) {
            try {
                $this->appendPdfBytes(
                    $merged,
                    Storage::disk('public')->get($attachment->file_path)
                );
            } catch (Throwable $exception) {
                Log::warning('Evidence PDF tidak dapat digabungkan ke laporan tiket.', [
                    'ticket_id' => $ticket->id,
                    'attachment_id' => $attachment->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $merged->Output('S');
    }

    private function appendPdfBytes(Fpdi $target, string $bytes): void
    {
        $pageCount = $target->setSourceFile(StreamReader::createByString($bytes));

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $template = $target->importPage($pageNumber);
            $size = $target->getTemplateSize($template);
            $target->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $target->useTemplate($template);
        }
    }

    private function attachmentDataUri(Attachment $attachment): ?string
    {
        if (! Storage::disk('public')->exists($attachment->file_path)) {
            return null;
        }

        return 'data:'.($attachment->mime_type ?: 'application/octet-stream').';base64,'.base64_encode(
            Storage::disk('public')->get($attachment->file_path)
        );
    }

    private function localFileDataUri(string $path, string $mimeType): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode((string) file_get_contents($path));
    }

    private function signatureQrDataUri(mixed $signature): ?string
    {
        if (! is_array($signature) || $signature === []) {
            return null;
        }

        $value = data_get($signature, 'portal_signature_url')
            ?: data_get($signature, 'portal_qr_payload');
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (is_string($value) && $value !== '') {
            try {
                $svg = QrCode::format('svg')
                    ->size(180)
                    ->margin(1)
                    ->errorCorrection('H')
                    ->generate($value);

                return 'data:image/svg+xml;base64,'.base64_encode($svg);
            } catch (Throwable $exception) {
                Log::warning('QR signature Portal gagal dibuat untuk laporan GA.', [
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $qrUrl = data_get($signature, 'portal_qr_url');
        if (! is_string($qrUrl) || $qrUrl === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)->get($qrUrl);
            if ($response->successful()) {
                return 'data:'.($response->header('Content-Type') ?: 'image/png').';base64,'.base64_encode($response->body());
            }
        } catch (Throwable $exception) {
            Log::warning('Gambar QR Portal gagal diambil untuk laporan GA.', [
                'url' => $qrUrl,
                'error' => $exception->getMessage(),
            ]);
        }

        return null;
    }
}
