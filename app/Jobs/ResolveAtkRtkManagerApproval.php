<?php

namespace App\Jobs;

use App\Models\Master\Ticket;
use App\Services\SatsetInitialApprovalService;
use App\Support\GaSubmissionDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResolveAtkRtkManagerApproval implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $ticketId, public readonly string $ticketNo)
    {
        $this->onConnection(GaSubmissionDelivery::CONNECTION)
            ->onQueue(GaSubmissionDelivery::QUEUE)
            ->beforeCommit();
    }

    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(SatsetInitialApprovalService $approval): void
    {
        if ($ticket = $this->ticket()) {
            $approval->resolveQueued($ticket);
        }
    }

    public function failed(): void
    {
        if ($ticket = $this->ticket()) {
            app(SatsetInitialApprovalService::class)->markResolutionFailed($ticket);
        }
    }

    private function ticket(): ?Ticket
    {
        return Ticket::where('ticket_no', $this->ticketNo)->find($this->ticketId);
    }
}
