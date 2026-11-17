<?php

namespace App\Support;

use Closure;

final class GaSubmissionTiming
{
    private array $stages = [];

    public static function measure(string $stage, Closure $operation): mixed
    {
        $timing = app()->bound('request') ? request()->attributes->get(self::class) : null;
        if (! $timing instanceof self) {
            return $operation();
        }

        $started = hrtime(true);
        try {
            return $operation();
        } finally {
            $samples = $timing->stages[$stage] ?? ['ns' => 0, 'calls' => 0];
            $timing->stages[$stage] = [
                'ns' => $samples['ns'] + hrtime(true) - $started,
                'calls' => $samples['calls'] + 1,
            ];
        }
    }

    public function stages(): array
    {
        return array_map(fn (array $sample) => [
            'ms' => round($sample['ns'] / 1e6, 1),
            'calls' => $sample['calls'],
        ], $this->stages);
    }
}
