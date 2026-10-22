<?php

namespace App\Providers;

use App\Models\Master\Ticket;
use App\Observers\TicketObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Ticket::observe(TicketObserver::class);

        if (app()->environment('production') || (bool) env('APP_FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }
    }
}
