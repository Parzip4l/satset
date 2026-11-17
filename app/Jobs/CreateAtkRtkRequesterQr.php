<?php

namespace App\Jobs;

use App\Models\Master\Ticket;
use App\Services\AtkRtkRequesterQr;
use App\Support\GaSubmissionDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class CreateAtkRtkRequesterQr implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $ticketId, public readonly string $ticketNo)
    {
        $this->onConnection(GaSubmissionDelivery::CONNECTION)
            ->onQueue(GaSubmissionDelivery::QUEUE)
            ->afterCommit();
    }

    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(AtkRtkRequesterQr $qr): void
    {
        $ticket = Ticket::where('ticket_no', $this->ticketNo)->find($this->ticketId);
        if ($ticket && data_get($ticket->payload, 'request_type') === 'atk_rtk' && ! $qr->ensure($ticket)) {
            throw new RuntimeException('QR pemohon ATK/RTK belum tersedia untuk tiket '.$this->ticketNo.'.');
        }
    }
}
