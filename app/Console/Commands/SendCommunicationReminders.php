<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Trial;
use App\Services\CommunicationCenter;
use Illuminate\Console\Command;

class SendCommunicationReminders extends Command
{
    protected $signature = 'communications:send-reminders {--days=3 : Days before expiry}';
    protected $description = 'Queue deduplicated trial and subscription expiry reminders';

    public function handle(CommunicationCenter $center): int
    {
        $days = (int) $this->option('days');
        if ($days < 1 || $days > 30) {
            $this->error('Days must be between 1 and 30.');
            return self::FAILURE;
        }

        $end = now()->addDays($days);
        $count = 0;

        Trial::with('tenant')->where('is_active',true)->whereNull('converted_at')
            ->where('ends_at','>',now())->where('ends_at','<=',$end)
            ->chunkById(100,function ($trials) use ($center,&$count): void {
                foreach ($trials as $trial) {
                    $tenant = $trial->tenant;
                    if (! $tenant || $tenant->status !== 'trial') continue;
                    $center->emit('trial.ending',$tenant,null,[
                        'platform_name'=>config('app.name','POS Cloud'),
                        'business_name'=>$tenant->name,
                        'trial_end_date'=>$trial->ends_at->format('Y-m-d'),
                        'business_url'=>route('business.subscription.index',$tenant),
                    ],'trial-ending-'.$trial->id.'-'.$trial->ends_at->format('Ymd'));
                    $count++;
                }
            });

        Subscription::with('tenant')->where('status','active')
            ->whereNotNull('ends_at')->where('ends_at','>',now())
            ->where('ends_at','<=',$end)
            ->chunkById(100,function ($subscriptions) use ($center,&$count): void {
                foreach ($subscriptions as $subscription) {
                    $tenant = $subscription->tenant;
                    if (! $tenant || $tenant->status !== 'active') continue;
                    $center->emit('subscription.ending',$tenant,null,[
                        'platform_name'=>config('app.name','POS Cloud'),
                        'business_name'=>$tenant->name,
                        'subscription_end_date'=>$subscription->ends_at->format('Y-m-d'),
                        'business_url'=>route('business.subscription.index',$tenant),
                    ],'subscription-ending-'.$subscription->id.'-'.$subscription->ends_at->format('Ymd'));
                    $count++;
                }
            });

        $this->info("Reviewed {$count} expiring records (duplicate-safe).");
        return self::SUCCESS;
    }
}
