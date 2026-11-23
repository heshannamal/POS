<?php

namespace App\Console\Commands;

use App\Models\BillingInvoice;
use App\Models\Tenant;
use App\Services\BillingMoney;
use App\Services\CommunicationCenter;
use Illuminate\Console\Command;

class SendBillingInvoiceReminders extends Command
{
    protected $signature='billing:remind-invoices {--days=2 : Age of pending review requests before alert}';
    protected $description='Remind platform billing reviewers about old pending receipts, without asking tenants to pay twice';

    public function handle(CommunicationCenter $center): int
    {
        $days=(int)$this->option('days');
        if ($days<1 || $days>30) {
            $this->error('Days must be between 1 and 30.');
            return self::FAILURE;
        }
        $count=0;
        BillingInvoice::with('tenant')->where('status','pending')
            ->where('issued_at','<=',now()->subDays($days))
            ->orderBy('id')->chunkById(100,function ($items) use ($center,&$count):void {
                foreach ($items as $invoice) {
                    $tenant=$invoice->tenant;
                    if (!$tenant || $tenant->trashed()) continue;
                    $center->emit('billing.review_overdue',$tenant,null,[
                        'platform_name'=>config('app.name','POS Cloud'),
                        'business_name'=>$tenant->name,
                        'invoice_number'=>$invoice->number,
                        'invoice_amount'=>BillingMoney::format($invoice->total_minor),
                        'currency'=>$invoice->currency,
                        'invoice_url'=>route('platform.billing.invoices.show',$invoice),
                    ],'pending-review-'.$invoice->id);
                    $count++;
                }
            });
        $this->info("Reviewed {$count} old pending invoices. No payment demand or charge sent to customers.");
        return self::SUCCESS;
    }
}
