<?php

namespace App\Console\Commands;

use App\Models\Master\Status;
use App\Models\Master\Ticket;
use App\Models\User;
use Illuminate\Console\Command;

class CloseHandedOverAtkRtkTickets extends Command
{
    protected $signature = 'tickets:close-handed-over-atk-rtk
        {--dry-run : Show affected tickets without updating them}
        {--actor-id= : User ID recorded in ticket history}';

    protected $description = 'Close ATK/RTK tickets that were already handed over but still have an open ticket status';

    public function handle(): int
    {
        $closedStatusId = Status::where('name', 'Closed')->value('id');

        if (! $closedStatusId) {
            $this->error('Status "Closed" tidak ditemukan di master statuses.');

            return self::FAILURE;
        }

        $query = Ticket::query()
            ->with('status')
            ->where('payload->request_type', 'atk_rtk')
            ->where('payload->workflow_status', 'HANDED_OVER')
            ->where(function ($query) use ($closedStatusId) {
                $query->whereNull('status_id')
                    ->orWhere('status_id', '!=', $closedStatusId);
            });

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Tidak ada tiket ATK/RTK handed over yang perlu ditutup.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Ditemukan {$total} tiket yang akan diubah ke Closed:");
            (clone $query)
                ->orderBy('id')
                ->limit(50)
                ->get(['id', 'ticket_no', 'status_id', 'payload'])
                ->each(function (Ticket $ticket) {
                    $this->line("- {$ticket->ticket_no} (ID {$ticket->id}, status sekarang: ".($ticket->status?->name ?? 'null').')');
                });

            if ($total > 50) {
                $this->line('...dan '.($total - 50).' tiket lainnya.');
            }

            return self::SUCCESS;
        }

        $actorId = $this->resolveActorId();
        if (! $actorId) {
            $this->warn('User untuk history tidak ditemukan. Status tetap diupdate, tetapi history backfill tidak dibuat.');
        }

        $updated = 0;
        (clone $query)
            ->orderBy('id')
            ->chunkById(100, function ($tickets) use ($closedStatusId, $actorId, &$updated) {
                foreach ($tickets as $ticket) {
                    $ticket->update(['status_id' => $closedStatusId]);

                    if ($actorId) {
                        $ticket->histories()->create([
                            'user_id' => $actorId,
                            'status_id' => $closedStatusId,
                            'action' => 'Backfill: ATK/RTK sudah handover, status tiket ditutup otomatis.',
                        ]);
                    }

                    $updated++;
                }
            });

        $this->info("Berhasil menutup {$updated} tiket ATK/RTK yang sudah handover.");

        return self::SUCCESS;
    }

    private function resolveActorId(): ?int
    {
        $optionActorId = $this->option('actor-id');
        if ($optionActorId && User::whereKey($optionActorId)->exists()) {
            return (int) $optionActorId;
        }

        return User::query()
            ->where('role', 'admin')
            ->value('id')
            ?: User::query()->value('id');
    }
}
