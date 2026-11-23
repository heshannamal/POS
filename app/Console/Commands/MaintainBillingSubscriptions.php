<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MaintainBillingSubscriptions extends Command
{
    protected $signature='billing:maintain {--dry-run : Preview changes without writing}';
    protected $description='Mark expired verified subscription periods past due, expired or cancelled; never charge cards';

    public function handle(): int
    {
        $grace=max(0,min(60,(int)PlatformSetting::valueFor('billing.grace_days','7')));
        $count=0;
        Subscription::whereIn('status',['active','past_due'])
            ->whereNotNull('ends_at')->where('ends_at','<=',now())
            ->orderBy('id')->chunkById(100,function ($items) use ($grace,&$count):void {
                foreach ($items as $item) {
                    DB::transaction(function () use ($item,$grace,&$count):void {
                        $subscription=Subscription::whereKey($item->id)->lockForUpdate()->first();
                        if (!$subscription || !in_array($subscription->status,['active','past_due'],true)
                            || !$subscription->ends_at || $subscription->ends_at->isFuture()) return;
                        $new = $subscription->cancel_at_period_end
                            ? 'cancelled'
                            : ($subscription->ends_at->copy()->addDays($grace)->isPast() ? 'expired':'past_due');
                        if ($new===$subscription->status) return;
                        $count++;
                        if ($this->option('dry-run')) return;
                        $before=$subscription->status;
                        $subscription->update(['status'=>$new]);
                        AuditLog::create([
                            'tenant_id'=>$subscription->tenant_id,
                            'action'=>'billing.subscription.period_ended',
                            'subject_type'=>Subscription::class,'subject_id'=>$subscription->id,
                            'metadata'=>['from'=>$before,'to'=>$new,'automatic_payment_collected'=>false],
                        ]);
                    });
                }
            });
        $this->info(($this->option('dry-run')?'Would update ':'Updated ').$count.' subscription periods. No card was charged.');
        return self::SUCCESS;
    }
}
