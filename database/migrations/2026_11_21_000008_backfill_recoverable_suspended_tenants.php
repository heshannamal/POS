<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Earlier versions suspended businesses by pausing their subscription.
        // Recover the previous state only where the retained subscription
        // source tells us whether they had a trial or active plan.
        DB::table('tenants')
            ->join('subscriptions', 'subscriptions.tenant_id', '=', 'tenants.id')
            ->where('tenants.status', 'suspended')
            ->whereNull('tenants.lifecycle_previous_status')
            ->select(['tenants.id', 'subscriptions.source'])
            ->orderBy('tenants.id')
            ->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    $previous = $row->source === 'trial' ? 'trial' : (
                        in_array($row->source, ['manual', 'asset_review', 'card_review'], true)
                            ? 'active' : null
                    );
                    if ($previous !== null) {
                        DB::table('tenants')->where('id', $row->id)
                            ->update(['lifecycle_previous_status' => $previous]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Do not erase lifecycle information that may have changed after
        // this backfill ran.
    }
};
