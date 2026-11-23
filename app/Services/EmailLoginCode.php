<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmailLoginCode
{
    public function send(User $user, ?string $ip = null): void
    {
        $code = (string) random_int(100000, 999999);
        $now = now();
        $key = config('app.key');
        if (! is_string($key) || $key === '') {
            throw new \RuntimeException('APP_KEY must be configured before issuing security codes.');
        }
        DB::transaction(function () use ($user, $ip, $code, $now, $key): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('email_login_codes')->where('user_id', $user->id)->lockForUpdate()->first();
            if ($existing && $now->diffInSeconds($existing->last_sent_at, false) > -60) {
                throw ValidationException::withMessages(['code' => 'Please wait at least one minute before requesting another code.']);
            }
            // Send inside the transaction: if the SMTP request fails, no new code is committed.
            // Lock on the parent user serializes competing resend requests.
            Mail::raw("Your POS Cloud administrator sign-in code is {$code}. It expires in 10 minutes. If you did not request it, do not share this code.", function ($message) use ($user): void {
                $message->to($user->email)->subject('POS Cloud sign-in verification code');
            });
            DB::table('email_login_codes')->updateOrInsert(
                ['user_id' => $user->id],
                ['code_hash' => hash_hmac('sha256', $code, $key),
                 'expires_at' => $now->copy()->addMinutes(10), 'last_sent_at' => $now,
                 'attempts' => 0, 'created_at' => $now, 'updated_at' => $now]
            );
            AuditLog::create(['actor_id' => $user->id, 'action' => 'security.email_code.sent',
                'subject_type' => User::class, 'subject_id' => $user->id, 'ip_address' => $ip,
                'metadata' => ['delivery' => 'email']]);
        });
    }

    public function verify(User $user, string $code, ?string $ip = null): void
    {
        $passed = DB::transaction(function () use ($user, $code, $ip): bool {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $entry = DB::table('email_login_codes')->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $entry || now()->greaterThan($entry->expires_at) || $entry->attempts >= 5) {
                return false;
            }
            $key = (string) config('app.key');
            $valid = hash_equals($entry->code_hash, hash_hmac('sha256', $code, $key));
            if ($valid) {
                DB::table('email_login_codes')->where('user_id', $user->id)->delete();
                AuditLog::create(['actor_id' => $user->id, 'action' => 'security.email_code.verified',
                    'subject_type' => User::class, 'subject_id' => $user->id, 'ip_address' => $ip]);
                return true;
            }
            DB::table('email_login_codes')->where('user_id', $user->id)
                ->update(['attempts' => $entry->attempts + 1, 'updated_at' => now()]);
            return false;
        });
        if (! $passed) {
            throw ValidationException::withMessages(['code' => 'Code is invalid, expired or has exceeded the maximum attempts. Request a new code if needed.']);
        }
    }
}