<?php

namespace App\Support;

use App\Mail\TicketCreated;
use App\Models\Master\Ticket;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

final class GaSubmissionDelivery
{
    public const CONNECTION = 'database';

    public const QUEUE = 'ga-notifications';

    public const APPROVAL_QUEUE = 'ga-approvals';

    public static function usesQueue(Ticket $ticket): bool
    {
        return in_array(data_get($ticket->payload, 'request_type'), ['consumption', 'atk_rtk'], true)
            && GaSubmissionTiming::measure('queue_check', fn () => Schema::connection(config('queue.connections.database.connection'))
                ->hasTable(config('queue.connections.database.table', 'jobs')));
    }

    public static function sendMail(Ticket $ticket, string $email, string $recipientType): void
    {
        $mail = new TicketCreated($ticket, $recipientType);
        $recipient = Mail::to($email);

        if (self::usesQueue($ticket)) {
            GaSubmissionTiming::measure('mail_enqueue', fn () => $recipient->queue(
                $mail->onConnection(self::CONNECTION)->onQueue(self::QUEUE)->afterCommit(),
            ));

            return;
        }

        GaSubmissionTiming::measure('mail', fn () => $recipient->send($mail));
    }
}
