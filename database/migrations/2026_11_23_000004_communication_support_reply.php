<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $event = 'support.customer_replied';
        $definition = config('communications.events.'.$event);
        if (! $definition) return;
        $now = now();
        DB::table('communication_templates')->insertOrIgnore([
            'event_key' => $event, 'name' => $definition['name'],
            'subject' => 'Customer replied to ticket #{{ticket_id}}',
            'body_markdown' => "The customer replied to ticket #{{ticket_id}} for **{{business_name}}**.\n\n[View reply]({{ticket_url}})",
            'allowed_variables' => json_encode($definition['variables']),
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('communication_rules')->insertOrIgnore([
            'event_key' => $event, 'is_enabled' => true,
            'send_email' => false, 'send_in_app' => true,
            'to_owner' => false, 'to_actor' => false,
            'to_platform' => true, 'extra_emails' => '[]',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Never destroy edited templates on rollback.
    }
};
