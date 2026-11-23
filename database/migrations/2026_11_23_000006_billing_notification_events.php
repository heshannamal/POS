<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $events = [
            'billing.invoice_issued' => [
                'subject' => 'Invoice {{invoice_number}} for {{business_name}}',
                'body' => "Your subscription invoice {{invoice_number}} for **{{business_name}}** is available.\n\nAmount: {{currency}} {{invoice_amount}}\n\n[View invoice]({{invoice_url}})",
            ],
            'billing.refund_recorded' => [
                'subject' => 'Credit note for invoice {{invoice_number}}',
                'body' => "An externally verified refund was recorded against invoice {{invoice_number}}.\n\n[View credit note in billing history]({{invoice_url}})",
            ],
        ];
        foreach ($events as $event=>$data) {
            $definition = config('communications.events',[])[$event] ?? null;
            if (!$definition) continue;
            DB::table('communication_templates')->insertOrIgnore([
                'event_key'=>$event,'name'=>$definition['name'],
                'subject'=>$data['subject'],'body_markdown'=>$data['body'],
                'allowed_variables'=>json_encode($definition['variables']),
                'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('communication_rules')->insertOrIgnore([
                'event_key'=>$event,'is_enabled'=>true,'send_email'=>false,'send_in_app'=>true,
                'to_owner'=>true,'to_actor'=>false,'to_platform'=>false,
                'extra_emails'=>'[]','created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve already customized templates and logged notifications.
    }
};
