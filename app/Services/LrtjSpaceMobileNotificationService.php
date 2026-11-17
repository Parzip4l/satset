<?php

namespace App\Services;

use App\Jobs\DeliverGaSubmissionNotification;
use App\Models\Master\Approval;
use App\Models\Master\Notification as WebNotification;
use App\Models\Master\Ticket;
use App\Models\User;
use App\Support\GaSubmissionTiming;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LrtjSpaceMobileNotificationService
{
    public function notifyTicketCreated(Ticket $ticket, bool $background = false): void
    {
        $ticket->loadMissing(['requester', 'status']);

        $title = 'Permintaan SatSet baru';
        $body = "{$ticket->ticket_no} dibuat oleh ".($ticket->requester?->name ?: 'pengguna SatSet').'.';

        $payload = [
            'event_type' => 'ticket_created',
            'ticket_id' => (string) $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'ticket_title' => $ticket->title,
            'request_type' => data_get($ticket->payload, 'request_type'),
            'status' => $this->ticketStatusLabel($ticket),
            'requester_name' => $ticket->requester?->name,
            'requester_email' => $ticket->requester?->email,
            'title' => $title,
            'body' => $body,
            'priority' => 'high',
        ];

        $requesterEmail = $ticket->requester?->email;
        if ($requesterEmail && $this->acquireDedupe('ticket_created', $requesterEmail, (string) $ticket->id)) {
            $requesterPayload = array_merge($payload, ['recipient_email' => $requesterEmail]);
            $this->notifyRecipient($ticket, $ticket->requester, $requesterPayload, $background);
        }

        $this->notifyAdminAndGaRecipients($payload, $ticket, [$requesterEmail], $background);
    }

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

        if (! $this->acquireDedupe('ticket_status_changed', $requester->email, $ticket->id, $status)) {
            return;
        }

        $title = 'Status SatSet berubah';
        $body = "{$ticket->ticket_no} sekarang {$status}.";

        $this->recordWebNotification($requester, $title, $body, $ticket);

        $payload = [
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
        ];

        $this->send($payload);
        $this->notifyAdminAndGaRecipients($payload, $ticket, [$requester->email]);
    }

    public function notifyApprovalRequested(Ticket $ticket, Approval $approval, bool $background = false): void
    {
        $approval->loadMissing('approver');
        $approver = $approval->approver;
        if (! $approver?->email) {
            return;
        }

        if (! $this->acquireDedupe('ticket_approval_requested', $approver->email, $ticket->id, $approval->id)) {
            return;
        }

        $title = 'Approval SatSet menunggu Anda';
        $body = "{$ticket->ticket_no} membutuhkan approval Anda.";

        if (\App\Support\Sppd::is($ticket)) {
            $this->recordWebNotification($approver, $title, $body, $ticket);

            return;
        }

        $payload = [
            'event_type' => 'ticket_approval_requested',
            'recipient_email' => $approver->email,
            'ticket_id' => (string) $ticket->id,
            'approval_id' => (string) $approval->id,
            'ticket_no' => $ticket->ticket_no,
            'ticket_title' => $ticket->title,
            'request_type' => data_get($ticket->payload, 'request_type'),
            'status' => $this->ticketStatusLabel($ticket),
            'title' => $title,
            'body' => $body,
            'priority' => 'high',
        ];

        $this->notifyRecipient($ticket, $approver, $payload, $background);
        $this->notifyAdminAndGaRecipients($payload, $ticket, [$approver->email], $background);
    }

    public function notifyApprovalDecided(Ticket $ticket, Approval $approval, User $actor, string $status, ?string $note = null): void
    {
        $ticket->loadMissing(['requester', 'status']);
        $requester = $ticket->requester;
        if (! $requester?->email) {
            return;
        }

        $normalizedStatus = strtolower($status);
        if (! $this->acquireDedupe('ticket_approval_decided', $requester->email, $ticket->id, $approval->id, $normalizedStatus)) {
            return;
        }
        if (\App\Support\Sppd::is($ticket)) {
            $this->recordWebNotification($requester, 'Status Pengajuan SPPD', "{$ticket->ticket_no} · ".\App\Support\Sppd::label($ticket), $ticket);

            return;
        }

        $decisionLabel = $normalizedStatus === 'approved' ? 'disetujui' : 'ditolak';
        $title = 'Approval SatSet '.$decisionLabel;
        $body = "{$ticket->ticket_no} telah {$decisionLabel} oleh {$actor->name}.";

        $this->recordWebNotification($requester, $title, $body, $ticket);

        $payload = [
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
        ];

        $this->send($payload);
        $this->notifyAdminAndGaRecipients($payload, $ticket, [$requester->email]);
    }

    public function notifyTicketAssigned(Ticket $ticket, ?User $assignedUser): void
    {
        if (! $assignedUser?->email) {
            return;
        }

        if (! $this->acquireDedupe('ticket_assigned', $assignedUser->email, $ticket->id, $assignedUser->id)) {
            return;
        }

        $title = 'Ticket SatSet ditujukan ke Anda';
        $body = "{$ticket->ticket_no} ditugaskan ke Anda.";

        $this->recordWebNotification($assignedUser, $title, $body, $ticket);

        $payload = [
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
        ];

        $this->send($payload);
        $this->notifyAdminAndGaRecipients($payload, $ticket, [$assignedUser->email]);
    }

    private function notifyAdminAndGaRecipients(
        array $payload,
        Ticket $ticket,
        array $exceptEmails = [],
        bool $background = false,
    ): void {
        // IT recipients are selected by the future IT domain event handler, never GA broadcast.
        if (\App\Support\ItAccess::isIt($ticket)) {
            return;
        }

        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        $excluded = collect($exceptEmails)
            ->filter()
            ->map(fn ($email) => strtolower((string) $email));

        User::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(TRIM(role)) IN (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                'admin',
                'superadmin',
                'super_admin',
                'ga',
                'bum',
                'general affair',
                'general affairs',
                'bagian umum',
                'operasional ga',
            ])
            ->get()
            ->each(function (User $recipient) use ($payload, $ticket, $excluded, $background): void {
                if ($excluded->contains(strtolower((string) $recipient->email))) {
                    return;
                }

                $recipientPayload = array_merge($payload, [
                    'recipient_email' => $recipient->email,
                    'recipient_scope' => 'admin_ga_all_requests',
                ]);
                $dedupeMarker = hash('sha256', json_encode($recipientPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                if (! $this->acquireDedupe('ga_copy', $recipient->email, (string) $ticket->id, $dedupeMarker)) {
                    return;
                }

                $this->notifyRecipient($ticket, $recipient, $recipientPayload, $background);
            });
    }

    private function notifyRecipient(Ticket $ticket, User $recipient, array $payload, bool $background): void
    {
        $this->recordWebNotification($recipient, $payload['title'], $payload['body'], $ticket, ! $background);

        if ($background) {
            $mobileEnabled = config('satset.lrtj_space_notifications.enabled', true)
                && filled(config('satset.lrtj_space_notifications.shared_secret'));
            if (! $mobileEnabled && ! app(BrowserWebPushService::class)->configured()) {
                return;
            }

            GaSubmissionTiming::measure('notification_enqueue', function () use ($ticket, $recipient, $payload): void {
                DeliverGaSubmissionNotification::dispatch((int) $ticket->id, (int) $recipient->id, $payload);
            });

            return;
        }

        $this->send($payload);
    }

    public function deliverQueued(Ticket $ticket, User $recipient, array $payload): void
    {
        $this->sendBrowserPush($recipient, $payload['title'], $payload['body'], route('ticket.show', $ticket), $ticket);
        $this->send($payload);
    }

    private function recordWebNotification(?User $recipient, string $title, string $message, Ticket $ticket, bool $browserPush = true): void
    {
        if (! $recipient?->id) {
            return;
        }

        $url = route(\App\Support\Sppd::is($ticket) ? 'sppd.show' : 'ticket.show', $ticket);

        if (Schema::hasTable('notifications')) {
            WebNotification::create([
                'user_id' => $recipient->id,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => false,
            ]);
        }

        if ($browserPush) {
            $this->sendBrowserPush($recipient, $title, $message, $url, $ticket);
        }
    }

    private function sendBrowserPush(User $recipient, string $title, string $message, string $url, Ticket $ticket): void
    {
        GaSubmissionTiming::measure('browser_push', fn () => app(BrowserWebPushService::class)->sendToUser($recipient, $title, $message, $url, [
            'ticket_id' => (string) $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'tag' => 'satset-ticket-'.$ticket->id,
        ]));
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
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $endpoint = rtrim((string) config('satset.lrtj_space_notifications.base_url'), '/').
            '/'.ltrim((string) config('satset.lrtj_space_notifications.endpoint'), '/');

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

            $response = GaSubmissionTiming::measure('portal_notification', fn () => $client->withBody($body, 'application/json')->post($endpoint));
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

    private function acquireDedupe(string ...$parts): bool
    {
        $key = 'satset:mobile-notification:'.hash('sha256', implode('|', $parts));

        return Cache::add($key, true, now()->addSeconds(30));
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
