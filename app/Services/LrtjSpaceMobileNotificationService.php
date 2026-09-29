<?php

namespace App\Services;

use App\Models\Master\Approval;
use App\Models\Master\Notification as WebNotification;
use App\Models\Master\Ticket;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LrtjSpaceMobileNotificationService
{
    public function notifyTicketStatusChanged(Ticket $ticket, ?User $actor = null, ?string $previousStatus = null): void
    {
        $ticket->loadMissing(['requester', 'status']);
        $requester = $ticket->requester;
        if (! $requester?->email) {
            return;
        }

        $status = $this->ticketStatusLabel($ticket);
        if ($previousStatus !== null && $previousStatus === $status) {
            return;
        }

        $title = 'Status SatSet berubah';
        $body = "{$ticket->ticket_no} sekarang {$status}.";

        $this->recordWebNotification($requester, $title, $body, $ticket);

        $this->send([
            'event_type' => 'ticket_status_changed',
            'recipient_email' => $requester->email,
            'ticket_id' => (string) $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'ticket_title' => $ticket->title,
            'request_type' => data_get($ticket->payload, 'request_type'),
            'status' => $status,
            'previous_status' => $previousStatus,
            'actor_name' => $actor?->name,
            'title' => $title,
            'body' => $body,
            'priority' => 'high',
        ]);
    }

    public function notifyApprovalRequested(Ticket $ticket, Approval $approval): void
    {
        $approval->loadMissing('approver');
        $approver = $approval->approver;
        if (! $approver?->email) {
            return;
        }

        $title = 'Approval SatSet menunggu Anda';
        $body = "{$ticket->ticket_no} membutuhkan approval Anda.";

        $this->recordWebNotification($approver, $title, $body, $ticket);

        $this->send([
            'event_type' => 'ticket_approval_requested',
            'recipient_email' => $approver->email,
            'ticket_id' => (string) $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'ticket_title' => $ticket->title,
            'request_type' => data_get($ticket->payload, 'request_type'),
            'status' => $this->ticketStatusLabel($ticket),
            'title' => $title,
            'body' => $body,
            'priority' => 'high',
        ]);
    }

    public function notifyApprovalDecided(Ticket $ticket, Approval $approval, User $actor, string $status, ?string $note = null): void
    {
        $ticket->loadMissing(['requester', 'status']);
        $requester = $ticket->requester;
        if (! $requester?->email) {
            return;
        }

        $normalizedStatus = strtolower($status);
        $decisionLabel = $normalizedStatus === 'approved' ? 'disetujui' : 'ditolak';
        $title = 'Approval SatSet '.$decisionLabel;
        $body = "{$ticket->ticket_no} telah {$decisionLabel} oleh {$actor->name}.";

        $this->recordWebNotification($requester, $title, $body, $ticket);

        $this->send([
            'event_type' => 'ticket_approval_decided',
            'recipient_email' => $requester->email,
            'ticket_id' => (string) $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'ticket_title' => $ticket->title,
            'request_type' => data_get($ticket->payload, 'request_type'),
            'status' => $this->ticketStatusLabel($ticket),
            'approval_id' => (string) $approval->id,
            'approval_status' => $normalizedStatus,
            'approver_name' => $actor->name,
            'approval_note' => $note,
            'title' => $title,
            'body' => $body,
            'priority' => 'high',
        ]);
    }

    public function notifyTicketAssigned(Ticket $ticket, ?User $assignedUser): void
    {
        if (! $assignedUser?->email) {
            return;
        }

        $title = 'Ticket SatSet ditujukan ke Anda';
        $body = "{$ticket->ticket_no} ditugaskan ke Anda.";

        $this->recordWebNotification($assignedUser, $title, $body, $ticket);

        $this->send([
            'event_type' => 'ticket_assigned',
            'recipient_email' => $assignedUser->email,
            'ticket_id' => (string) $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'ticket_title' => $ticket->title,
            'request_type' => data_get($ticket->payload, 'request_type'),
            'status' => $this->ticketStatusLabel($ticket),
            'title' => $title,
            'body' => $body,
            'priority' => 'high',
        ]);
    }

    private function recordWebNotification(?User $recipient, string $title, string $message, Ticket $ticket): void
    {
        if (! $recipient?->id || ! Schema::hasTable('notifications')) {
            return;
        }

        WebNotification::create([
            'user_id' => $recipient->id,
            'title' => $title,
            'message' => $message,
            'url' => route('ticket.show', $ticket),
            'is_read' => false,
        ]);
    }

    private function send(array $payload): void
    {
        if (! config('satset.lrtj_space_notifications.enabled', true)) {
            return;
        }

        $secret = (string) config('satset.lrtj_space_notifications.shared_secret');
        if ($secret === '') {
            Log::warning('SatSet mobile notification skipped because shared secret is missing.');

            return;
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        $endpoint = rtrim((string) config('satset.lrtj_space_notifications.base_url'), '/') .
            '/' . ltrim((string) config('satset.lrtj_space_notifications.endpoint'), '/');

        try {
            $client = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Satset-Timestamp' => $timestamp,
                'X-Satset-Signature' => $signature,
            ])->timeout((int) config('satset.lrtj_space_notifications.timeout', 15));

            if (! config('satset.lrtj_space_notifications.verify_ssl', true)) {
                $client = $client->withoutVerifying();
            }

            $response = $client->withBody($body, 'application/json')->post($endpoint);
            if (! $response->successful()) {
                Log::warning('LRTJ Space mobile notification bridge failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'ticket_id' => $payload['ticket_id'] ?? null,
                ]);
            }
        } catch (ConnectionException $exception) {
            Log::warning('LRTJ Space mobile notification bridge connection failed.', [
                'message' => $exception->getMessage(),
                'ticket_id' => $payload['ticket_id'] ?? null,
            ]);
        }
    }

    private function ticketStatusLabel(Ticket $ticket): string
    {
        if (in_array($ticket->status->name ?? null, ['Closed', 'Resolved'], true)) {
            return $ticket->status->name;
        }

        return data_get($ticket->payload, 'workflow_status')
            ?: ($ticket->status->name ?? 'Updated');
    }
}
