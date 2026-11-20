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
    $user->platform_role = 'super_admin';
    $user->save();

    $this->info('Platform administrator is ready: '.$email);
})->purpose('Securely create or promote a verified platform super administrator');


/**
 * Local-only temporary platform login, for development and UI verification.
 * Run: php artisan platform:temporary-login --hours=24
 */
Artisan::command('platform:temporary-login {--hours=24}', function (): int {
    if (! in_array((string) config('app.env'), ['local', 'testing'], true)) {
        $this->error('Temporary admin creation is disabled outside local development.');
        return 1;
    }

    $hours = filter_var($this->option('hours'), FILTER_VALIDATE_INT);
    if ($hours === false || $hours < 1 || $hours > 72) {
        $this->error('Duration must be an integer from 1 to 72 hours.');
        return 1;
    }

    do {
        $email = 'temp-admin-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(12)).'@pos.test';
    } while (User::where('email', $email)->exists());

    $password = \Illuminate\Support\Str::random(30);
    $user = User::create([
        'name' => 'Temporary Platform Admin',
        'email' => $email,
        'password' => $password,
    ]);
    $user->forceFill([
        'is_platform_admin' => true,
        'platform_role' => 'super_admin',
        'email_verified_at' => now(),
        'temporary_admin_expires_at' => now()->addHours($hours),
    ])->save();

    $this->newLine();
    $this->info('Temporary development login created.');
    $this->line('Login URL: '.route('login'));
    $this->line('Email: '.$email);
    $this->line('Password: '.$password);
    $this->line('Expires at: '.$user->temporary_admin_expires_at->format('Y-m-d H:i:s T'));
    $this->warn('Save these details now. The password will not be displayed again.');
    $this->warn('This account is restricted to local development and expires automatically.');

    return 0;
})->purpose('Create a random, expiring super-admin login for local development only');
