<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('platform:make-admin {email} {--name=Platform Administrator}', function (): void {
    $email = mb_strtolower(trim((string) $this->argument('email')));
    $name = (string) $this->option('name');

    $validator = Validator::make(['email' => $email, 'name' => $name], [
        'email' => ['required', 'email'],
        'name' => ['required', 'string', 'max:120'],
    ]);

    if ($validator->fails()) {
        $this->error($validator->errors()->first());
        return;
    }

    $password = $this->secret('New admin password (min 12 characters, letters and numbers)');
    $check = Validator::make(['password' => $password], [
        'password' => ['required', Password::min(12)->letters()->numbers()],
    ]);

    if ($check->fails()) {
        $this->error($check->errors()->first());
        return;
    }

    $user = User::firstOrNew(['email' => $email]);
    if ($user->exists && ! $this->confirm('Promote this existing account to platform admin?')) {
        $this->warn('Cancelled.');
        return;
    }

    $user->name = $name;
    $user->password = Hash::make($password);
    $user->email_verified_at ??= now();
    $user->is_platform_admin = true;
    $user->save();

    $this->info('Platform administrator is ready: '.$email);
})->purpose('Securely create or promote a verified platform super administrator');
