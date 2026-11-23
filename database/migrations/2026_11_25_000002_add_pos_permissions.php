<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            ['pos.sales','POS sales and receipt access'],
            ['pos.inventory.manage','Manage POS catalog and inventory'],
            ['pos.register.manage','Manage POS registers and shifts'],
            ['pos.returns.manage','Issue POS returns and refunds'],
            ['pos.reports.view','View POS financial and stock reports'],
        ];
        foreach ($permissions as [$key,$label]) {
            DB::table('permissions')->updateOrInsert(
                ['key'=>$key],['label'=>$label,'group'=>'POS']
            );
        }
        // Preserve all existing assignments; owner bypasses permissions.
        // Other staff must receive explicit new permissions from business role editor.
    }

    public function down(): void
    {
        $keys=['pos.sales','pos.inventory.manage','pos.register.manage','pos.returns.manage','pos.reports.view'];
        $ids=DB::table('permissions')->whereIn('key',$keys)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id',$ids)->delete();
        DB::table('permissions')->whereIn('id',$ids)->delete();
    }
};