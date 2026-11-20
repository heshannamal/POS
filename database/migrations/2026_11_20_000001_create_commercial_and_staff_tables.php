<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('category', 60)->default('General');
            $table->boolean('is_available')->default(false);
            $table->unsignedBigInteger('monthly_price_minor')->default(0);
            $table->timestamps();
        });
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('monthly_price_minor')->default(0);
            $table->string('currency', 3)->default('LKR');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('plan_modules', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->primary(['plan_id', 'module_id']);
        });
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('trialing');
            $table->string('source', 24)->default('trial');
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'ends_at']);
        });
        Schema::create('tenant_module_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('source', 24)->default('manual');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'module_id']);
        });
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->string('value', 255);
            $table->timestamps();
        });
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 191);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'email']);
        });

        $now = now();
        $modules = [
            ['code' => 'staff.core', 'name' => 'Employees & permissions', 'category' => 'Administration', 'is_available' => 1],
            ['code' => 'pos.core', 'name' => 'Core POS', 'category' => 'Sales', 'is_available' => 0],
            ['code' => 'inventory.basic', 'name' => 'Basic inventory', 'category' => 'Inventory', 'is_available' => 0],
            ['code' => 'reports.basic', 'name' => 'Business reports', 'category' => 'Analytics', 'is_available' => 0],
            ['code' => 'inventory.advanced', 'name' => 'Advanced inventory', 'category' => 'Inventory', 'is_available' => 0],
            ['code' => 'ecommerce.store', 'name' => 'E-commerce storefront', 'category' => 'Commerce', 'is_available' => 0],
            ['code' => 'crm.loyalty', 'name' => 'Customer loyalty', 'category' => 'Commerce', 'is_available' => 0],
            ['code' => 'whatsapp.automatic', 'name' => 'Automated WhatsApp receipts', 'category' => 'Integrations', 'is_available' => 0],
            ['code' => 'branches.multi', 'name' => 'Multi-branch management', 'category' => 'Operations', 'is_available' => 0],
        ];
        foreach ($modules as $item) {
            DB::table('modules')->insert(array_merge($item, [
                'description' => 'Feature licensing is ready; operational screens are released by development phase.',
                'monthly_price_minor' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }
        foreach ([
            ['slug' => 'starter', 'name' => 'Starter', 'description' => 'Core business access. Prices must be configured before publishing.'],
            ['slug' => 'business', 'name' => 'Business', 'description' => 'Prepared for advanced inventory and reports.'],
            ['slug' => 'commerce', 'name' => 'Commerce', 'description' => 'Prepared for online selling and CRM extensions.'],
        ] as $plan) {
            DB::table('plans')->insert(array_merge($plan, [
                'monthly_price_minor' => 0, 'currency' => 'LKR', 'is_public' => false,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }
        $plans = DB::table('plans')->pluck('id', 'slug');
        $moduleIds = DB::table('modules')->pluck('id', 'code');
        $inclusions = [
            'starter' => ['staff.core', 'pos.core', 'inventory.basic'],
            'business' => ['staff.core', 'pos.core', 'inventory.basic', 'inventory.advanced', 'reports.basic', 'branches.multi'],
            'commerce' => ['staff.core', 'pos.core', 'inventory.basic', 'inventory.advanced', 'reports.basic', 'branches.multi', 'ecommerce.store', 'crm.loyalty'],
        ];
        foreach ($inclusions as $plan => $codes) {
            foreach ($codes as $code) {
                DB::table('plan_modules')->insert(['plan_id' => $plans[$plan], 'module_id' => $moduleIds[$code]]);
            }
        }
        DB::table('platform_settings')->insert([
            'key' => 'trial.default_days', 'value' => (string) config('saas.default_trial_days', 14),
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Existing tenants from the first development phase keep their data and get a
        // starter subscription in the same database. This is not a payment event.
        DB::table('tenants')->orderBy('id')->chunkById(100, function ($tenants) use ($plans, $now) {
            foreach ($tenants as $tenant) {
                DB::table('subscriptions')->insert([
                    'tenant_id' => $tenant->id, 'plan_id' => $plans['starter'],
                    'status' => $tenant->status === 'trial' ? 'trialing' : ($tenant->status === 'active' ? 'active' : 'paused'),
                    'source' => $tenant->status === 'active' ? 'manual' : 'trial',
                    'started_at' => $tenant->created_at ?? $now,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('tenant_module_grants');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_modules');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('modules');
    }
};
