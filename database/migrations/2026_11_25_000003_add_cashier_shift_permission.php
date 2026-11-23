<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key'=>'pos.shifts.operate'],
            ['label'=>'Open, manage and close own POS shift','group'=>'POS']
        );
    }

    public function down(): void
    {
        $id=DB::table('permissions')->where('key','pos.shifts.operate')->value('id');
        if($id){
            DB::table('permission_role')->where('permission_id',$id)->delete();
            DB::table('permissions')->where('id',$id)->delete();
        }
    }
};