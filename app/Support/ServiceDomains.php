<?php

namespace App\Support;

use App\Models\Master\ServiceDomain;
use Illuminate\Support\Facades\Schema;

class ServiceDomains
{
    public const DEFINITIONS = [
        'atk_rtk' => ['name' => 'ATK/RTK', 'description' => 'Alat tulis dan perlengkapan rumah tangga kantor.', 'icon' => 'bi-box-seam', 'route' => 'ticket.atk-rtk.create'],
        'consumption' => ['name' => 'Konsumsi', 'description' => 'Konsumsi rapat dan kegiatan.', 'icon' => 'bi-cup-hot', 'route' => 'ticket.konsumsi.create'],
        'ga_request_finding' => ['name' => 'Permintaan & Temuan', 'description' => 'Kebutuhan dukungan dan temuan fasilitas GA.', 'icon' => 'bi-building-gear', 'route' => 'ticket.ga-permintaan-temuan.create'],
        'it' => ['name' => 'Layanan IT', 'description' => 'Gangguan dan permintaan layanan teknologi informasi.', 'icon' => 'bi-laptop', 'route' => 'it.request'],
    ];

    public static function catalog(): array
    {
        $stored = Schema::hasTable('service_domains') ? ServiceDomain::all()->keyBy('code') : collect();

        return collect(self::DEFINITIONS)->map(function (array $definition, string $code) use ($stored) {
            $domain = $stored->get($code);

            return [
                'code' => $code,
                'name' => $domain?->name ?? $definition['name'],
                'description' => $domain?->description ?? $definition['description'],
                'icon' => in_array($domain?->icon, array_column(self::DEFINITIONS, 'icon'), true) ? $domain->icon : $definition['icon'],
                'route' => $definition['route'],
                'accepting_requests' => $code === 'it' ? false : ($domain?->is_active ?? true),
                'sort_order' => $domain?->sort_order ?? array_search($code, array_keys(self::DEFINITIONS), true),
            ];
        })->sortBy('sort_order')->values()->all();
    }
}
