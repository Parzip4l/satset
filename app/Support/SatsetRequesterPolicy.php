<?php

namespace App\Support;

use Illuminate\Support\Str;

class SatsetRequesterPolicy
{
    public static function routesDirectlyToGa(?string $email): bool
    {
        $normalizedEmail = Str::lower(trim((string) $email));
        if ($normalizedEmail === '') {
            return false;
        }

        return collect(config('satset.approval_resolver.direct_ga_requester_emails', []))
            ->map(fn ($configuredEmail) => Str::lower(trim((string) $configuredEmail)))
            ->contains($normalizedEmail);
    }
}
