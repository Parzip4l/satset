<?php

namespace App\Jobs\It;

use App\Services\It\OperationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverOperationNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 45;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(OperationDelivery $delivery): void
    {
        $delivery->deliver($this->deliveryId);
    }

    public function failed(\Throwable $exception): void
    {
        app(OperationDelivery::class)->failed($this->deliveryId, $exception);
    }
}
