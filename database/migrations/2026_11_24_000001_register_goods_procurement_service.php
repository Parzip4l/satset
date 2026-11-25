<?php

use App\Support\ProcurementAccess;
use App\Support\ServiceDomains;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_domains')) {
            return;
        }

        $definition = ServiceDomains::DEFINITIONS[ProcurementAccess::TYPE];
        DB::table('service_domains')->insertOrIgnore([
            'code' => ProcurementAccess::TYPE,
            'name' => $definition['name'],
            'description' => $definition['description'],
            'icon' => $definition['icon'],
            'sort_order' => (int) DB::table('service_domains')->max('sort_order') + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (Schema::hasColumn('requests', 'service_domain_id')) {
            DB::table('requests')->whereNull('service_domain_id')
                ->where('payload->request_type', ProcurementAccess::TYPE)
                ->update(['service_domain_id' => DB::table('service_domains')->where('code', ProcurementAccess::TYPE)->value('id')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_domains')) {
            return;
        }

        $id = DB::table('service_domains')->where('code', ProcurementAccess::TYPE)->value('id');
        if ($id && Schema::hasColumn('requests', 'service_domain_id')) {
            DB::table('requests')->where('service_domain_id', $id)->update(['service_domain_id' => null]);
        }
        DB::table('service_domains')->where('code', ProcurementAccess::TYPE)->delete();
    }
};
