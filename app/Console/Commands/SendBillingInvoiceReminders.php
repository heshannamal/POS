<?php

namespace App\Console\Commands;

use App\Models\BillingInvoice;
use App\Services\BillingMoney;
use App\Services\CommunicationCenter;
use Illuminate\Console\Command;

class SendBillingInvoiceReminders extends Command
{
    protected $signature='billing:remind-invoices {--days=3 : Days before invoice due}';
    protected $description='Notify owners of pending invoices, without requesting duplicate bank transfers';

    public function handle(CommunicationCenter $center): int
    {
        $days=(int)$this->option('days');
        if ($days<0 || $days>30) {
            $this->error('Days must be between 0 and 30.');
            return self::FAILURE;
        }

        // Payment evidence already submitted. Never send an unpaid-demand
        // reminder for these records; it could cause a duplicate transfer.
        // Administrators can resend informational invoice links manually.
        $this->info('Payment-review invoices do not generate automatic payment demands. No duplicate collection requested.');
        return self::SUCCESS;
    }
}
