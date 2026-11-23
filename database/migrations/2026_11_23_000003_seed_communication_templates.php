<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $subject = [
            'business.created' => 'Welcome to {{platform_name}} — {{business_name}}',
            'payment.submitted' => 'Payment #{{payment_id}} awaiting review',
            'payment.approved' => 'Payment #{{payment_id}} approved',
            'payment.rejected' => 'Payment #{{payment_id}} review update',
            'trial.ending' => 'Your trial for {{business_name}} is ending',
            'subscription.ending' => 'Renewal reminder for {{business_name}}',
            'support.opened' => 'New ticket #{{ticket_id}} from {{business_name}}',
            'support.replied' => 'Update on support ticket #{{ticket_id}}',
            'security.alert' => 'Security alert — {{alert_type}}',
            'staff.invited' => 'Employee invitation issued for {{business_name}}',
        ];
        $body = [
            'business.created' => "Hello {{owner_name}},\n\nYour business **{{business_name}}** is now registered with {{platform_name}}.\n\n[Open business dashboard]({{business_url}})",
            'payment.submitted' => "A payment request #{{payment_id}} for **{{business_name}}** requires review.\n\nAmount: {{payment_amount}} {{currency}}\n\n[Review request]({{payment_url}})",
            'payment.approved' => "Your payment request #{{payment_id}} has been approved.\n\n{{business_name}} — {{payment_amount}} {{currency}}\n\n[Open billing]({{payment_url}})",
            'payment.rejected' => "Your payment request #{{payment_id}} could not be approved.\n\nPlease review the subscription page for details.\n\n[Open billing]({{payment_url}})",
            'trial.ending' => "Your trial for **{{business_name}}** ends on {{trial_end_date}}.\n\n[Manage subscription]({{business_url}})",
            'subscription.ending' => "The subscription for **{{business_name}}** ends on {{subscription_end_date}}.\n\n[Review subscription]({{business_url}})",
            'support.opened' => "A new ticket #{{ticket_id}} was opened by {{business_name}}.\n\n**{{ticket_subject}}**\n\n[View support ticket]({{ticket_url}})",
            'support.replied' => "There is a new response on ticket #{{ticket_id}} for **{{business_name}}**.\n\n[View ticket]({{ticket_url}})",
            'security.alert' => "A security alert of type **{{alert_type}}** was recorded.\n\n[Open Security Center]({{security_url}})",
            'staff.invited' => "An employee invitation has been created for **{{business_name}}**.\n\n[View employees]({{business_url}})",
        ];

        foreach (config('communications.events', []) as $event => $definition) {
            DB::table('communication_templates')->insertOrIgnore([
                'event_key' => $event,
                'name' => $definition['name'],
                'subject' => $subject[$event] ?? config('communications.default_subject'),
                'body_markdown' => $body[$event] ?? config('communications.default_body'),
                'allowed_variables' => json_encode($definition['variables']),
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('communication_rules')->insertOrIgnore([
                'event_key' => $event,
                'is_enabled' => true, 'send_email' => false, 'send_in_app' => true,
                'to_owner' => $definition['to_owner'] ?? false,
                'to_actor' => false,
                'to_platform' => $definition['to_platform'] ?? false,
                'extra_emails' => '[]',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive rollback: preserve administrator edits and delivery history.
    }
};
