<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  foreach ([
   'pos.suppliers.manage'=>'Manage purchasing suppliers',
   'pos.purchases.manage'=>'Create and approve purchase orders',
   'pos.receiving.manage'=>'Receive purchased goods',
   'pos.payables.manage'=>'Record supplier payments',
   'pos.purchasing.reports'=>'View purchasing reports'
  ] as $key=>$label)DB::table('permissions')->updateOrInsert(['key'=>$key],['label'=>$label,'group'=>'Purchasing']);
 }
 public function down():void {
  $keys=['pos.suppliers.manage','pos.purchases.manage','pos.receiving.manage','pos.payables.manage','pos.purchasing.reports'];
  $ids=DB::table('permissions')->whereIn('key',$keys)->pluck('id');
  DB::table('permission_role')->whereIn('permission_id',$ids)->delete();
  DB::table('permissions')->whereIn('id',$ids)->delete();
 }
};