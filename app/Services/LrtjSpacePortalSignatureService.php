<?php

namespace App\Services;

use App\Models\Master\Ticket;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LrtjSpacePortalSignatureService
{
    public function createRequesterSignature(Ticket $ticket, User $requester): ?array
    {
        $signature = $this->createRequesterSignatureWithSigner($ticket, [
            'id' => (string) $requester->id,
            'name' => $requester->name,
            'email' => $requester->email,
        ]);

        if ($signature) {
            return $signature;
        }

        try {
            $portalRequester = app(LrtjSpaceApprovalResolverService::class)->resolveRequester($ticket);
        } catch (\Throwable $exception) {
            Log::warning('Portal signature requester fallback resolver failed.', [
                'message' => $exception->getMessage(),
                'ticket_no' => $ticket->ticket_no,
                'requester_email' => $requester->email,
            ]);

            return null;
        }

        return $this->createRequesterSignatureWithSigner($ticket, [
            'id' => isset($portalRequester['id']) ? (string) $portalRequester['id'] : (string) $requester->id,
            'name' => $portalRequester['name'] ?? $requester->name,
            'email' => $portalRequester['email'] ?? $requester->email,
        ]);
    }

    private function createRequesterSignatureWithSigner(Ticket $ticket, array $signer): ?array
    {
        return $this->createSignature([
            'module' => 'satset',
            'signature_type' => 'requester_submission',
            'role' => 'requester',
            'ticket' => [
                'id' => (string) $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'title' => $ticket->title,
                'request_type' => data_get($ticket->payload, 'request_type'),
            ],
            'signer' => $signer,
            'signed_at' => optional($ticket->created_at ?: now())->toIso8601String(),
        ]);
    }

    private function createSignature(array $payload): ?array
    {
        $secret = (string) config('satset.portal_signatures.shared_secret');
        if ($secret === '') {
            Log::warning('Portal signature skipped because shared secret is missing.', [
                'signature_type' => $payload['signature_type'] ?? null,
                'ticket_no' => data_get($payload, 'ticket.ticket_no'),
            ]);

            return null;
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            Log::warning('Portal signature skipped because payload cannot be encoded.');

            return null;
        }

        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $endpoint = rtrim((string) config('satset.portal_signatures.base_url'), '/').
            '/'.ltrim((string) config('satset.portal_signatures.endpoint'), '/');

        try {
            $client = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Satset-Timestamp' => $timestamp,
                'X-Satset-Signature' => $signature,
            ])->timeout((int) config('satset.portal_signatures.timeout', 15));

            if (! config('satset.portal_signatures.verify_ssl', true)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->withBody($body, 'application/json')->post($endpoint);
        } catch (ConnectionException $exception) {
            Log::warning('Portal signature request failed.', [
                'message' => $exception->getMessage(),
                'ticket_no' => data_get($payload, 'ticket.ticket_no'),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Portal signature request rejected.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'ticket_no' => data_get($payload, 'ticket.ticket_no'),
            ]);

            return null;
        }

        return $this->normalizeSignature($response->json());
    }

    public function normalizeSignature(mixed $response): ?array
    {
        if (! is_array($response)) {
            return null;
        }

        $source = data_get($response, 'data.signature')
            ?? data_get($response, 'data')
            ?? data_get($response, 'signature')
            ?? $response;

        if (! is_array($source)) {
            return null;
        }

        $signatureId = $source['signature_id']
            ?? $source['id']
            ?? $source['portal_signature_id']
            ?? null;
        $verifyUrl = $source['verify_url']
            ?? $source['verification_url']
            ?? $source['signature_url']
            ?? $source['portal_signature_url']
            ?? null;
        $qrUrl = $source['qr_url']
            ?? $source['qr_image_url']
            ?? $source['qrcode_url']
            ?? $source['portal_qr_url']
            ?? null;

        if (! $signatureId && ! $verifyUrl && ! $qrUrl) {
            return null;
        }

        return array_filter([
            'portal_signature_id' => $signatureId ? (string) $signatureId : null,
            'portal_signature_url' => $verifyUrl ? (string) $verifyUrl : null,
            'portal_qr_url' => $qrUrl ? (string) $qrUrl : null,
            'portal_qr_payload' => $source['qr_payload'] ?? $source['payload'] ?? null,
        ], fn ($value) => $value !== null);
    }
}
