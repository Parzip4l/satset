<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Master\ServiceDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ItServiceController extends Controller
{
    public function index()
    {
        abort_unless(Schema::hasTable('service_domains'), 503, 'Fondasi layanan IT belum tersedia.');
        $service = ServiceDomain::where('code', 'it')->firstOrFail();

        return view('settings.it-service', compact('service'));
    }

    public function update(Request $request)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        abort_unless(Schema::hasTable('service_domains'), 503, 'Fondasi layanan IT belum tersedia.');
        $service = ServiceDomain::where('code', 'it')->firstOrFail();
        $old = $service->is_active;
        $service->update(['is_active' => $data['is_active']]);
        Log::notice('IT service availability changed', ['actor_id' => $request->user()->id, 'from' => $old, 'to' => (bool) $data['is_active']]);

        return redirect()->route('settings.it-service.index')->with('success', 'Status layanan IT berhasil diperbarui.');
    }
}
