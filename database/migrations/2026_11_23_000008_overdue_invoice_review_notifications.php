<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $key='billing.review_overdue';
        $definition=config('communications.events',[])[$key];
        DB::table('communication_templates')->insertOrIgnore([
            'event_key'=>$key,'name'=>$definition['name'],
            'subject'=>'Payment review overdue: {{invoice_number}}',
            'body_markdown'=>"An invoice for **{{business_name}}** is still awaiting independent payment verification.\n\nInvoice: {{invoice_number}} · {{currency}} {{invoice_amount}}\n\n[Open payment review]({{invoice_url}})",
            'allowed_variables'=>json_encode($definition['variables']),
            'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('communication_rules')->insertOrIgnore([
            'event_key'=>$key,'is_enabled'=>true,'send_email'=>false,
            'send_in_app'=>true,'to_owner'=>false,'to_actor'=>false,
            'to_platform'=>true,'extra_emails'=>'[]',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function down(): void
    {
        // Preserve edited templates.
    }
};
