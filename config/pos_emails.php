<?php

/*
|--------------------------------------------------------------------------
| Editable POS Cloud email variables
|--------------------------------------------------------------------------
| Edit the text when your approved wording is ready. The code supplies ONLY
| listed variables. No database query or PHP execution is performed on email
| template text. Never include passwords, card information or secrets.
*/
return [
    'password_reset' => [
        'subject' => '{{platform_name}} — Password reset',
        'greeting' => 'Hello {{user_name}},',
        'intro' => 'A password reset was requested for your POS Cloud account.',
        'action' => 'Reset password',
        'outro' => 'This link expires after {{expires_minutes}} minutes. If you did not request it, contact support.',
    ],
    'staff_invitation' => [
        'subject' => 'Invitation to {{business_name}} — {{platform_name}}',
        'body' => "Hello,\n\nYou are invited to join {{business_name}} on {{platform_name}} as {{role_name}}.\n\nAccept your invitation: {{invitation_url}}\n\nThis invitation expires in {{expires_days}} days. Sign in or register using {{employee_email}}.",
    ],
];
