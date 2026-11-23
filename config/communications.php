<?php

// The variables here are the only placeholders administrators may use
// in editable templates. They are data: never execute template text as PHP.
return [
    'events' => [
        'business.created' => [
            'name' => 'New business registered',
            'variables' => ['platform_name','business_name','owner_name','owner_email','business_url'],
            'to_owner' => true, 'to_platform' => true, 'platform_roles' => ['super_admin','operations'],
        ],
        'payment.submitted' => [
            'name' => 'Payment submitted for review',
            'variables' => ['platform_name','business_name','payment_id','payment_amount','currency','payment_url'],
            'to_platform' => true, 'platform_roles' => ['super_admin','billing'],
        ],
        'payment.approved' => [
            'name' => 'Payment approved',
            'variables' => ['platform_name','business_name','payment_id','payment_amount','currency','payment_url'],
            'to_owner' => true,
        ],
        'payment.rejected' => [
            'name' => 'Payment rejected',
            'variables' => ['platform_name','business_name','payment_id','payment_amount','currency','payment_url'],
            'to_owner' => true,
        ],
        'trial.ending' => [
            'name' => 'Trial ending soon',
            'variables' => ['platform_name','business_name','trial_end_date','business_url'],
            'to_owner' => true,
        ],
        'subscription.ending' => [
            'name' => 'Subscription renewal reminder',
            'variables' => ['platform_name','business_name','subscription_end_date','business_url'],
            'to_owner' => true,
        ],
        'support.opened' => [
            'name' => 'New support ticket',
            'variables' => ['platform_name','business_name','ticket_id','ticket_subject','ticket_url'],
            'to_platform' => true, 'platform_roles' => ['super_admin','support','operations'],
        ],
        'support.customer_replied' => [
            'name' => 'Customer replied to support ticket',
            'variables' => ['platform_name','business_name','ticket_id','ticket_subject','ticket_url'],
            'to_platform' => true, 'platform_roles' => ['super_admin','support','operations'],
        ],
        'support.replied' => [
            'name' => 'Support ticket reply',
            'variables' => ['platform_name','business_name','ticket_id','ticket_subject','ticket_url'],
            'to_owner' => true,
        ],
        'security.alert' => [
            'name' => 'Security alert',
            'variables' => ['platform_name','alert_type','alert_id','security_url'],
            'to_platform' => true, 'platform_roles' => ['super_admin'],
        ],
        'staff.invited' => [
            'name' => 'Employee invited',
            'variables' => ['platform_name','business_name','owner_name','business_url'],
            'to_owner' => true,
        ],
    ],
    'default_subject' => '{{platform_name}} — {{business_name}}',
    'default_body' => "Hello,\n\nThere is an update for {{business_name}}.\n\nOpen your POS Cloud dashboard for details.",
];
