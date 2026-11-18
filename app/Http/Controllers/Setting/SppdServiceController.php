<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Master\ServiceDomain;
use App\Support\Sppd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SppdServiceController extends Controller
{
    public function index()
    {
        return view('settings.sppd-service', ['service' => $this->service()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $service = $this->service();
        $wasActive = $service->is_active;
        $isActive = (bool) $data['is_active'];

        if ($wasActive !== $isActive) {
            $service->update(['is_active' => $isActive]);
            Log::notice('SPPD service availability changed', [
                'actor_id' => $request->user()->id,
                'from' => $wasActive,
                'to' => $isActive,
            ]);
        }

        return redirect()->route('settings.sppd-service.index')->with(
            'success',
            $isActive ? 'Pengajuan SPPD baru telah dibuka.' : 'Pengajuan SPPD baru telah ditutup.',
        );
    }

    public function updatePolicy(Request $request)
    {
        $data = $request->validate(['sppd_policy_url' => ['nullable', 'url:http,https', 'max:2000']]);
        $this->service()->update(['sppd_policy_url' => $data['sppd_policy_url'] ?? null]);

        return redirect()->route('settings.sppd-service.index')->with('success', 'Tautan SK Perjalanan Dinas disimpan.');
    }

    private function service(): ServiceDomain
    {
        $service = Schema::hasTable('service_domains')
            ? ServiceDomain::where('code', Sppd::TYPE)->first()
            : null;
        abort_unless($service, 503, 'Pengaturan status SPPD belum tersedia.');

        return $service;
    }
}
