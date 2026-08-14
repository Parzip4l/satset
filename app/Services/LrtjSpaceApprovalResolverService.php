<?php

namespace App\Services;

use App\Models\Master\Ticket;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LrtjSpaceApprovalResolverService
{
    public function resolveFirstApprover(Ticket $ticket): User
    {
        $response = $this->resolve($ticket);
        $approver = $this->extractApprover($response);

        if (! is_array($approver) || empty($approver['email'])) {
            Log::warning('LRTJ Space approval resolver response missing approver.', [
                'ticket_id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'requester_email' => $ticket->requester?->email ?: data_get($ticket->payload, 'reporter_email'),
                'response_keys' => array_keys($response),
                'data_keys' => is_array($response['data'] ?? null) ? array_keys($response['data']) : null,
            ]);

            $message = data_get($response, 'message')
                ?: 'Approval resolver tidak mengembalikan email approver untuk '.$ticket->ticket_no.'. Pastikan Portal memakai email pemohon '.($ticket->requester?->email ?: data_get($ticket->payload, 'reporter_email')).' dan mengirim data.steps[0].approver.email.';

            $this->fail($message);
        }

        return $this->findOrCreateApprover($approver);
    }

    private function resolve(Ticket $ticket): array
    {
        $secret = (string) config('satset.approval_resolver.shared_secret');
        if ($secret === '') {
            $this->fail('Shared secret approval resolver belum dikonfigurasi.');
        }

        $payload = $this->payload($ticket);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $this->fail('Payload approval resolver gagal dibuat.');
        }

        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $endpoint = rtrim((string) config('satset.approval_resolver.base_url'), '/').
            '/'.ltrim((string) config('satset.approval_resolver.endpoint'), '/');

        try {
            $client = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Satset-Timestamp' => $timestamp,
                'X-Satset-Signature' => $signature,
            ])->timeout((int) config('satset.approval_resolver.timeout', 15));

            if (! config('satset.approval_resolver.verify_ssl', true)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->withBody($body, 'application/json')->post($endpoint);
        } catch (ConnectionException $exception) {
            Log::warning('LRTJ Space approval resolver connection failed.', [
                'message' => $exception->getMessage(),
                'ticket_id' => $ticket->id,
            ]);

            $this->fail('Approval resolver tidak dapat dihubungi. Silakan coba lagi atau hubungi administrator.');
        }

        if ($response->status() === 422) {
            $message = data_get($response->json(), 'message')
                ?: Arr::first(Arr::flatten((array) data_get($response->json(), 'errors', [])))
                ?: $response->body();
            $this->fail('Approval resolver menolak request: '.trim((string) $message));
        }

        if (! $response->successful()) {
            Log::warning('LRTJ Space approval resolver failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'ticket_id' => $ticket->id,
            ]);

            $this->fail('Approval resolver gagal menentukan approver. Status: '.$response->status().'.');
        }

        $json = $response->json();
        if (! is_array($json)) {
            $this->fail('Approval resolver mengembalikan response yang tidak valid.');
        }

        return $json;
    }

    private function payload(Ticket $ticket): array
    {
        $ticket->loadMissing('requester');
        $requestType = (string) data_get($ticket->payload, 'request_type');
        $requesterEmail = (string) ($ticket->requester?->email ?: data_get($ticket->payload, 'reporter_email'));

        if ($requesterEmail === '') {
            $this->fail('Email pemohon kosong, approval resolver tidak dapat menentukan approver.');
        }

        return [
            'module' => 'satset',
            'request_type' => $requestType,
            'requester_email' => $requesterEmail,
            'amount' => $this->amount($ticket),
            'metadata' => [
                'ticket_id' => (string) $ticket->id,
                'ticket_no' => (string) $ticket->ticket_no,
            ],
        ];
    }

    private function amount(Ticket $ticket): float
    {
        foreach (['total_estimated_amount', 'estimated_amount', 'estimated_cost', 'actual_cost', 'amount'] as $key) {
            $value = data_get($ticket->payload, $key);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return 0.0;
    }

    private function extractApprover(array $response): ?array
    {
        $candidates = [
            data_get($response, 'data.steps.0.approver'),
            data_get($response, 'data.approver'),
            data_get($response, 'approver'),
            data_get($response, 'data.manager'),
            data_get($response, 'manager'),
            data_get($response, 'data.supervisor'),
            data_get($response, 'supervisor'),
            data_get($response, 'data.department_head'),
            data_get($response, 'department_head'),
            data_get($response, 'data.employee.manager'),
            data_get($response, 'employee.manager'),
            data_get($response, 'data.approvers.0'),
            data_get($response, 'approvers.0'),
            data_get($response, 'data.steps.0'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $email = $candidate['email']
                ?? $candidate['approver_email']
                ?? $candidate['manager_email']
                ?? $candidate['supervisor_email']
                ?? $candidate['department_head_email']
                ?? data_get($candidate, 'user.email')
                ?? data_get($candidate, 'employee.email')
                ?? data_get($candidate, 'manager.email')
                ?? data_get($candidate, 'supervisor.email');

            if (! $email) {
                continue;
            }

            return [
                'id' => $candidate['id']
                    ?? $candidate['approver_id']
                    ?? $candidate['manager_id']
                    ?? $candidate['supervisor_id']
                    ?? $candidate['user_id']
                    ?? data_get($candidate, 'user.id')
                    ?? data_get($candidate, 'employee.id'),
                'email' => $email,
                'name' => $candidate['name']
                    ?? $candidate['approver_name']
                    ?? $candidate['manager_name']
                    ?? $candidate['supervisor_name']
                    ?? $candidate['department_head_name']
                    ?? $candidate['full_name']
                    ?? data_get($candidate, 'user.name')
                    ?? data_get($candidate, 'employee.name')
                    ?? data_get($candidate, 'manager.name')
                    ?? data_get($candidate, 'supervisor.name')
                    ?? $email,
            ];
        }

        return null;
    }

    private function findOrCreateApprover(array $approver): User
    {
        $email = trim((string) ($approver['email'] ?? ''));
        $name = trim((string) ($approver['name'] ?? '')) ?: $email;
        $externalId = $approver['id'] ?? null;

        $user = User::where('email', $email)->first();
        if (! $user && is_numeric($externalId)) {
            $user = User::find((int) $externalId);
        }

        if ($user) {
            $updates = array_filter([
                'name' => $name,
                'email' => $email,
            ]);

            if (($user->role ?? null) === null) {
                $updates['role'] = 'approver';
            }

            $user->fill($updates)->save();

            return $user;
        }

        return User::forceCreate([
            'user_type' => 'karyawan',
            'nik' => null,
            'name' => $name,
            'email' => $email,
            'username' => $email,
            'phone' => '',
            'password' => Hash::make(Str::random(32)),
            'kartu_uang_1' => '',
            'kartu_uang_2' => null,
            'role' => 'approver',
        ]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'approval_resolver' => $message,
        ]);
    }
}
