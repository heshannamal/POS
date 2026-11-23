<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\BillingRefund;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\BillingMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingOperationsController extends Controller
{
    public function settings(): View
    {
        $settings = [
            'tax_rate' => number_format((int)PlatformSetting::valueFor('billing.tax_basis_points','0')/100,2,'.',''),
            'invoice_due_days' => (int)PlatformSetting::valueFor('billing.invoice_due_days','7'),
            'grace_days' => (int)PlatformSetting::valueFor('billing.grace_days','7'),
        ];
        return view('platform.billing.settings',compact('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tax_rate'=>['required','numeric','min:0','max:100','decimal:0,2'],
            'invoice_due_days'=>['required','integer','min:1','max:60'],
            'grace_days'=>['required','integer','min:0','max:60'],
        ]);
        $rate = BillingMoney::parse((string)$data['tax_rate']);
        DB::transaction(function () use ($request,$rate,$data): void {
            foreach ([
                'billing.tax_basis_points'=>(string)$rate,
                'billing.invoice_due_days'=>(string)$data['invoice_due_days'],
                'billing.grace_days'=>(string)$data['grace_days'],
            ] as $key=>$value) {
                PlatformSetting::updateOrCreate(['key'=>$key],['value'=>$value]);
            }
            AuditLog::create([
                'actor_id'=>$request->user()->id,'action'=>'billing.settings.updated',
                'metadata'=>[
                    'tax_basis_points'=>$rate,'invoice_due_days'=>$data['invoice_due_days'],
                    'grace_days'=>$data['grace_days'],
                ],'ip_address'=>$request->ip(),
            ]);
        });
        return back()->with('status','Billing defaults saved. Existing issued invoices retain their original tax and price.');
    }

    public function extend(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'days'=>['required','integer','min:1','max:365'],
            'reason'=>['required','string','min:10','max:1000'],
            'confirm_no_payment'=>['required','accepted'],
        ]);
        DB::transaction(function () use ($tenant,$data,$request): void {
            $lockedTenant=Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedTenant->status,['trial','active'],true)) {
                throw ValidationException::withMessages(['days'=>'Restore the business before extending its subscription.']);
            }
            $subscription=Subscription::where('tenant_id',$tenant->id)->lockForUpdate()->firstOrFail();
            if ($subscription->status === 'trialing') {
                throw ValidationException::withMessages(['days'=>'Trial extensions use the existing Trials module.']);
            }
            $end=$subscription->ends_at?->isFuture() ? $subscription->ends_at->copy() : now();
            $end->addDays((int)$data['days']);
            $subscription->update([
                'status'=>'active','ends_at'=>$end,
                'cancel_at_period_end'=>null,
            ]);
            $lockedTenant->update(['status'=>'active']);
            AuditLog::create([
                'tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
                'action'=>'billing.subscription.free_extension',
                'subject_type'=>Subscription::class,'subject_id'=>$subscription->id,
                'metadata'=>['days'=>(int)$data['days'],'ends_at'=>$end->toIso8601String(),
                    'reason'=>$data['reason'],'payment_collected'=>false],
                'ip_address'=>$request->ip(),
            ]);
        });
        return back()->with('status','Subscription extended manually without creating an invoice or collecting money.');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'from'=>['nullable','date_format:Y-m-d'],
            'to'=>['nullable','date_format:Y-m-d','after_or_equal:from'],
        ]);
    }

    private function financialSummary(array $filters): array
    {
        $invoices = BillingInvoice::query()->with('tenant:id,name')
            ->when($filters['from']??null,fn($q,$v)=>$q->whereDate('issued_at','>=',$v))
            ->when($filters['to']??null,fn($q,$v)=>$q->whereDate('issued_at','<=',$v))
            ->get();
        $invoiceIds = $invoices->pluck('id');
        $refunds = BillingRefund::whereIn('invoice_id',$invoiceIds)->get();
        $rows=[];
        foreach (['LKR','USD'] as $currency) {
            $items=$invoices->where('currency',$currency);
            $rows[]=[
                'currency'=>$currency,
                'issued_count'=>$items->count(),
                'paid_count'=>$items->where('status','paid')->count(),
                'gross_minor'=>(int)$items->where('status','paid')->sum('total_minor'),
                'unpaid_minor'=>(int)$items->where('status','pending')->sum('total_minor'),
                'refunded_minor'=>(int)$refunds->where('currency',$currency)->sum('amount_minor'),
            ];
        }
        return $rows;
    }

    public function report(Request $request): View
    {
        $filter=$this->filters($request);
        $summary=$this->financialSummary($filter);
        $recent=BillingInvoice::with('tenant:id,name,slug')->latest('id')->limit(12)->get();
        return view('platform.billing.report',compact('summary','recent'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filter=$this->filters($request);
        $summary=$this->financialSummary($filter);
        AuditLog::create([
            'actor_id'=>$request->user()->id,
            'action'=>'billing.report.exported',
            'metadata'=>['from'=>$filter['from']??null,'to'=>$filter['to']??null],
            'ip_address'=>$request->ip(),
        ]);
        return response()->streamDownload(function () use ($summary): void {
            $stream=fopen('php://output','w');
            fputcsv($stream,['Currency','Issued count','Paid count','Gross paid minor units',
                'Refunded minor units','Net paid minor units','Pending invoice minor units']);
            foreach ($summary as $row) {
                fputcsv($stream,[
                    $row['currency'],$row['issued_count'],$row['paid_count'],
                    $row['gross_minor'],$row['refunded_minor'],
                    $row['gross_minor']-$row['refunded_minor'],
                    $row['unpaid_minor'],
                ]);
            }
            fclose($stream);
        },'billing-summary-'.now()->format('Ymd-His').'.csv',[
            'Content-Type'=>'text/csv; charset=UTF-8',
            'X-Content-Type-Options'=>'nosniff',
            'Cache-Control'=>'private, no-store',
        ]);
    }
}
