<?php

namespace App\Services;

use App\Jobs\CreateAtkRtkRequesterQr;
use App\Models\Master\Ticket;
use App\Support\GaSubmissionDelivery;
use App\Support\GaSubmissionTiming;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AtkRtkRequesterQr
{
    public function __construct(private readonly LrtjSpacePortalSignatureService $signatures) {}

    public function queueForSubmission(Ticket $ticket): bool
    {
        if (data_get($ticket->payload, 'request_type') !== 'atk_rtk' || ! GaSubmissionDelivery::usesQueue($ticket)) {
            return false;
        }

        if (filled(config('satset.portal_signatures.shared_secret'))) {
            GaSubmissionTiming::measure('qr_enqueue', fn () => Bus::dispatch(new CreateAtkRtkRequesterQr((int) $ticket->id, $ticket->ticket_no)));
        }

        return true;
    }

    public function ensure(Ticket $ticket): bool
    {
        $lockSeconds = 2 * (int) config('satset.portal_signatures.timeout', 15)
            + (int) config('satset.approval_resolver.timeout', 15) + 15;

        try {
            return Cache::lock('satset:atk-requester-qr:'.$ticket->id, $lockSeconds)
                ->block(5, function () use ($ticket): bool {
                    $current = Ticket::where('ticket_no', $ticket->ticket_no)->with('requester')->find($ticket->id);
                    if (! $current || filled(data_get($current->payload, 'portal_signatures.requester'))) {
                        return true;
                    }
                    if (! $current->requester) {
                        return false;
                    }

                    $signature = $this->signatures->createRequesterSignature($current, $current->requester);
                    if (! $signature) {
                        return false;
                    }

                    DB::transaction(function () use ($ticket, $signature): void {
                        $current = Ticket::whereKey($ticket->id)->lockForUpdate()->first();
                        if ($current && $current->ticket_no === $ticket->ticket_no
                            && blank(data_get($current->payload, 'portal_signatures.requester'))) {
                            $payload = $current->payload ?? [];
                            $payload['portal_signatures']['requester'] = $signature;
                            $current->updateQuietly(['payload' => $payload]);
                        }
                    });

                    return true;
                });
        } catch (LockTimeoutException) {
            return false;
        }
    }
}
