<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class GaAccess
{
    public static function allowed(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $roleNames = collect([$user->role ?? null])
            ->filter()
            ->map(fn ($role) => Str::of((string) $role)->lower()->trim()->toString());

        if ($roleNames->contains(fn ($role) => in_array($role, [
            'admin',
            'ga',
            'bum',
            'general affair',
            'general affairs',
            'bagian umum',
            'operasional ga',
        ], true))) {
            return true;
        }

        $divisionName = Str::of((string) optional($user->division)->name)->lower()->trim()->toString();

        return self::matchesGaTeamName($divisionName);
    }

    private static function matchesGaTeamName(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (preg_match('/\b(ga|bum)\b/i', $value) === 1) {
            return true;
        }

        return Str::contains($value, [
            'general affair',
            'general affairs',
            'bagian umum',
            'operasional ga',
        ]);
    }
}
