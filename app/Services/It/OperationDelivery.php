<?php

namespace App\Services\It;

use App\Jobs\It\DeliverOperationNotification;
use App\Models\Master\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OperationDelivery
{
    public function enqueue(): int
    {
        $ids = DB::table('it_delivery_outbox')->where(fn ($q) => $q->where('status', 'pending')->orWhere(fn ($q) => $q->where('status', 'queued')->where('dispatched_at', '<', CarbonImmutable::now('UTC')->subMinutes(5))))->orderBy('id')->limit(500)->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$count) {
                $row = DB::table('it_delivery_outbox')->where('id', $id)->lockForUpdate()->first();
                if (! $row || ! in_array($row->status, ['pending', 'queued'], true)) {
                    return;
                }
                DB::table('it_delivery_outbox')->where('id', $id)->update(['status' => 'queued', 'dispatched_at' => CarbonImmutable::now('UTC'), 'updated_at' => CarbonImmutable::now('UTC')]);
                DeliverOperationNotification::dispatch($id)->onConnection('it_database')->onQueue('it')->afterCommit();
                $count++;
            });
        }

        return $count;
    }

    public function deliver(int $id): void
    {
        DB::table('it_delivery_outbox')->where('id', $id)->whereNotIn('status', ['delivered', 'skipped'])->increment('attempts');
        DB::transaction(function () use ($id) {
            $out = DB::table('it_delivery_outbox')->where('id', $id)->lockForUpdate()->first();
            if (! $out || in_array($out->status, ['delivered', 'skipped'], true)) {
                return;
            }
            $t = Ticket::find($out->request_id);
            $u = User::find($out->user_id);
            if (! $t || ! $u || ! $t->itDetail || ! app(TicketWorkflow::class)->staff($u, $t, $t->itDetail)) {
                DB::table('it_delivery_outbox')->where('id', $id)->update(['status' => 'skipped', 'last_error' => 'Akses penerima sudah tidak berlaku.', 'updated_at' => CarbonImmutable::now('UTC')]);

                return;
            }
            $this->resolveFailures($id);
            $event = DB::table('it_sla_events')->find($out->sla_event_id);
            $message = $event->action === 'communication_overdue' ? 'Pembaruan major incident sudah jatuh tempo.' : 'Peringatan target layanan IT memerlukan tindak lanjut.';
            DB::table('it_operation_notifications')->insertOrIgnore(['delivery_id' => $id, 'request_id' => $t->id, 'user_id' => $u->id, 'message' => $message, 'created_at' => CarbonImmutable::now('UTC')]);
            DB::table('it_delivery_outbox')->where('id', $id)->update(['status' => 'delivered', 'delivered_at' => CarbonImmutable::now('UTC'), 'last_error' => null, 'updated_at' => CarbonImmutable::now('UTC')]);
        }, 3);
    }

    private function resolveFailures(int $delivery): void
    {
        foreach (DB::table('it_queue_failed_jobs')->where('queue', 'it')->whereNull('resolved_at')->get(['id', 'payload']) as $row) {
            $payload = json_decode($row->payload, true);
            if (($payload['data']['commandName'] ?? null) === \App\Jobs\It\DeliverOperationNotification::class
                && str_contains($payload['data']['command'] ?? '', 's:10:"deliveryId";i:'.$delivery.';')) {
                DB::table('it_queue_failed_jobs')->where('id', $row->id)->update(['resolved_at' => CarbonImmutable::now('UTC')]);
            }
        }
    }

    public function failed(int $id, \Throwable $e): void
    {
        DB::table('it_delivery_outbox')->where('id', $id)->whereNotIn('status', ['delivered', 'skipped'])->update(['status' => 'failed', 'last_error' => mb_substr(get_class($e).': '.$e->getMessage(), 0, 1000), 'updated_at' => CarbonImmutable::now('UTC')]);
    }
}
