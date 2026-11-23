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
            'mode'=>['nullable',Rule::in(['summary','ledger'])],
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
        $paid=BillingInvoice::withSum('refunds','amount_minor')->where('status','paid')
            ->when($filter['from']??null,fn($q,$v)=>$q->whereDate('issued_at','>=',$v))
            ->when($filter['to']??null,fn($q,$v)=>$q->whereDate('issued_at','<=',$v))
            ->get();

        $groupRows=function ($groups, string $label) {
            return $groups->map(function ($items,$key) use ($label) {
                $first=$items->first();
                $gross=(int)$items->sum('total_minor');
                $refunded=(int)$items->sum('refunds_sum_amount_minor');
                return ['name'=>$label==='month'?$first->issued_at->format('Y-m'):($label==='plan'?$first->plan_name:$first->bill_to_name),
                    'currency'=>$first->currency,'count'=>$items->count(),
                    'gross_minor'=>$gross,'refunded_minor'=>$refunded,'net_minor'=>$gross-$refunded];
            })->sortByDesc('net_minor')->values()->take(25);
        };
        $byPlan=$groupRows($paid->groupBy(fn($i)=>$i->currency.'|'.$i->plan_name),'plan');
        $byTenant=$groupRows($paid->groupBy(fn($i)=>$i->currency.'|'.$i->tenant_id),'tenant');
        $byMonth=$groupRows($paid->groupBy(fn($i)=>$i->currency.'|'.$i->issued_at->format('Y-m')),'month')
            ->sortBy('name')->values();
        $recent=BillingInvoice::with('tenant:id,name,slug')->latest('id')->limit(12)->get();
        return view('platform.billing.report',compact('summary','recent','byPlan','byTenant','byMonth'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filter=$this->filters($request);
        $summary=$this->financialSummary($filter);
        $ledger=$filter['mode']??'summary';
        $invoices=$ledger==='ledger'
            ? BillingInvoice::withSum('refunds','amount_minor')
                ->when($filter['from']??null,fn($q,$v)=>$q->whereDate('issued_at','>=',$v))
                ->when($filter['to']??null,fn($q,$v)=>$q->whereDate('issued_at','<=',$v))
                ->orderBy('id')->get()
            : collect();
        AuditLog::create([
            'actor_id'=>$request->user()->id,
            'action'=>'billing.report.exported',
            'metadata'=>['from'=>$filter['from']??null,'to'=>$filter['to']??null,'mode'=>$ledger],
            'ip_address'=>$request->ip(),
        ]);
        return response()->streamDownload(function () use ($summary,$invoices,$ledger): void {
            $stream=fopen('php://output','w');
            if ($ledger==='ledger') {
                fputcsv($stream,['Invoice','Business','Currency','Status','Plan','Cycle',
                    'List minor','Plan discount minor','Coupon minor','Tax minor',
                    'Total minor','Refunded minor','Issue date','Paid date']);
                foreach ($invoices as $invoice) {
                    $safeCell = static function (string $value): string {
                        // Prevent spreadsheet formula injection in untrusted names.
                        return preg_match('/^[\s]*[=+@\-]/u',$value) ? "'".$value : $value;
                    };
                    $safeName=$safeCell((string)$invoice->bill_to_name);
                    fputcsv($stream,[
                        $invoice->number,$safeName,$invoice->currency,$invoice->status,
                        $safeCell($invoice->plan_name),$invoice->billing_cycle,
                        $invoice->list_price_minor,$invoice->plan_discount_minor,
                        $invoice->coupon_discount_minor,$invoice->tax_minor,
                        $invoice->total_minor,(int)$invoice->refunds_sum_amount_minor,
                        $invoice->issued_at?->format('Y-m-d'),$invoice->paid_at?->format('Y-m-d'),
                    ]);
                }
            } else {
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
            }
            fclose($stream);
        },'billing-'.$ledger.'-'.now()->format('Ymd-His').'.csv',[
            'Content-Type'=>'text/csv; charset=UTF-8',
            'X-Content-Type-Options'=>'nosniff',
            'Cache-Control'=>'private, no-store',
        ]);
    }
}
