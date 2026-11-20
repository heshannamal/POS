<?php

namespace App\Services;

class PosEmailVariables
{
    public static function render(string $template, string $field, array $variables): string
    {
        $text = (string) config('pos_emails.'.$template.'.'.$field, '');
        $safe = [];
        foreach ($variables as $name => $value) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', (string) $name)) {
                $safe['{{'.$name.'}}'] = (string) $value;
            }
        }

        return strtr($text, $safe);
    }

    public static function resetVariables(object $user, string $url): array
    {
        return [
            'platform_name' => config('app.name', 'POS Cloud'),
            'user_name' => $user->name,
            'user_email' => $user->email,
            'reset_url' => $url,
            'expires_minutes' => config('auth.passwords.users.expire', 60),
        ];
    }
}
