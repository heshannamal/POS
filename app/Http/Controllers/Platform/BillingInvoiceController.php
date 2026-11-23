<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\BillingRefund;
use App\Models\CommunicationRule;
use App\Models\CommunicationTemplate;
use App\Models\PlatformSetting;
use App\Services\BillingMoney;
use App\Services\BillingPdf;
use App\Services\CommunicationCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BillingInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'status'=>['nullable',Rule::in(['pending','paid','void'])],
            'currency'=>['nullable',Rule::in(['LKR','USD'])],
            'from'=>['nullable','date_format:Y-m-d'],
            'to'=>['nullable','date_format:Y-m-d','after_or_equal:from'],
            'search'=>['nullable','string','max:120'],
        ]);

        $query = BillingInvoice::with(['tenant:id,name,slug','payment:id,method,status'])
            ->withSum('refunds','amount_minor')->latest('id')
            ->when($filter['status'] ?? null, fn($q,$v)=>$q->where('status',$v))
            ->when($filter['currency'] ?? null,fn($q,$v)=>$q->where('currency',$v))
            ->when($filter['from'] ?? null,fn($q,$v)=>$q->whereDate('issued_at','>=',$v))
            ->when($filter['to'] ?? null,fn($q,$v)=>$q->whereDate('issued_at','<=',$v))
            ->when($filter['search'] ?? null, fn($q,$v)=>$q->where(fn($w)=>$w
                ->where('number','like','%'.$v.'%')
                ->orWhere('bill_to_name','like','%'.$v.'%')
                ->orWhereHas('tenant',fn($t)=>$t->where('name','like','%'.$v.'%'))));

        $invoices = $query->paginate(20)->withQueryString();
        $stats = [
            'pending'=>BillingInvoice::where('status','pending')->count(),
            'paid'=>BillingInvoice::where('status','paid')->count(),
            'void'=>BillingInvoice::where('status','void')->count(),
        ];
        return view('platform.billing.invoices',compact('invoices','stats'));
    }

    public function show(BillingInvoice $invoice): View
    {
        $invoice->load(['tenant','plan','payment.reviewer','refunds']);
        return view('platform.billing.show',compact('invoice'));
    }

    public function pdf(BillingInvoice $invoice, BillingPdf $pdf): Response
    {
        $type = request()->query('type','invoice');
        abort_unless(in_array($type,['invoice','receipt'],true),404);
        abort_if($type==='receipt' && $invoice->status!=='paid',404);
        $bytes = $pdf->invoice($invoice, $type==='receipt');
        return response($bytes,200,[
            'Content-Type'=>'application/pdf',
            'Content-Disposition'=>'attachment; filename="'.($type==='receipt'?$invoice->receipt_number:$invoice->number).'.pdf"',
            'Cache-Control'=>'private, no-store',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }

    public function refund(Request $request, BillingInvoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount'=>['required','numeric','min:0.01','max:10000000','decimal:0,2'],
            'external_reference'=>['required','string','max:120','regex:/^[A-Za-z0-9_.-]+$/',
                'unique:billing_refunds,external_reference'],
            'method'=>['required',Rule::in(['bank','card','other'])],
            'reason'=>['required','string','min:10','max:2000'],
            'confirm_external_refund'=>['required','accepted'],
        ]);
        $amount = BillingMoney::parse((string)$data['amount']);

        $refund = DB::transaction(function () use ($request,$invoice,$data,$amount): BillingRefund {
            $locked = BillingInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status!=='paid') {
                throw ValidationException::withMessages(['amount'=>'Only paid invoices may have verified refunds recorded.']);
            }
            $already = (int)$locked->refunds()->sum('amount_minor');
            if ($amount<1 || $amount+$already>$locked->total_minor) {
                throw ValidationException::withMessages(['amount'=>'Refund must be positive and cannot exceed the remaining paid amount.']);
            }
            if (BillingRefund::where('external_reference',$data['external_reference'])->exists()) {
                throw ValidationException::withMessages(['external_reference'=>'Reference has already been recorded.']);
            }

            $refund = BillingRefund::create([
                'invoice_id'=>$locked->id,'tenant_id'=>$locked->tenant_id,
                'recorded_by'=>$request->user()->id,
                'external_reference'=>$data['external_reference'],
                'method'=>$data['method'],'amount_minor'=>$amount,
                'currency'=>$locked->currency,'reason'=>$data['reason'],
                'verified_at'=>now(),
            ]);
            $refund->update(['credit_note_number'=>sprintf('CN-%08d',$refund->id)]);
            AuditLog::create([
                'tenant_id'=>$locked->tenant_id,'actor_id'=>$request->user()->id,
                'action'=>'billing.refund.recorded','subject_type'=>BillingRefund::class,
                'subject_id'=>$refund->id,
                'metadata'=>['invoice_id'=>$locked->id,'amount_minor'=>$amount,
                    'currency'=>$locked->currency,'external_reference'=>$data['external_reference'],
                    'no_gateway_transfer_executed'=>true],
                'ip_address'=>$request->ip(),
            ]);
            return $refund;
        },3);
        return redirect()->route('platform.billing.invoices.show',$invoice)
            ->with('status','Verified external refund recorded as '.$refund->credit_note_number.'. No money was transferred by POS Cloud.');
    }

    public function creditNote(BillingRefund $refund, BillingPdf $pdf): Response
    {
        return response($pdf->creditNote($refund),200,[
            'Content-Type'=>'application/pdf',
            'Content-Disposition'=>'attachment; filename="'.$refund->credit_note_number.'.pdf"',
            'Cache-Control'=>'private, no-store',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }

    public function resend(Request $request, BillingInvoice $invoice, CommunicationCenter $center): RedirectResponse
    {
        $invoice->load('tenant');
        if ($invoice->status==='void') {
            throw ValidationException::withMessages(['invoice'=>'Voided invoices cannot be resent.']);
        }
        if (PlatformSetting::valueFor('communications.email_enabled','0')!=='1') {
            throw ValidationException::withMessages(['invoice'=>'Enable Phase 5 email delivery and configure recipients first.']);
        }
        $rule = CommunicationRule::where('event_key','billing.invoice_issued')->first();
        $template = CommunicationTemplate::where('event_key','billing.invoice_issued')->first();
        if (! $rule?->is_enabled || ! $rule->send_email || ! $rule->to_owner || ! $template?->is_active) {
            throw ValidationException::withMessages(['invoice'=>'Enable the Invoice Issued email rule, owner recipient and template in Communication Center first.']);
        }
        $center->emit('billing.invoice_issued',$invoice->tenant,$request->user(),[
            'platform_name'=>config('app.name','POS Cloud'),
            'business_name'=>$invoice->bill_to_name,
            'invoice_number'=>$invoice->number,
            'invoice_amount'=>BillingMoney::format($invoice->total_minor),
            'currency'=>$invoice->currency,
            'invoice_url'=>route('business.billing.show',[$invoice->tenant,$invoice]),
        ],'invoice-resend-'.$invoice->id.'-'.\Illuminate\Support\Str::uuid());

        AuditLog::create([
            'tenant_id'=>$invoice->tenant_id,'actor_id'=>$request->user()->id,
            'action'=>'billing.invoice.reminder_requested','subject_type'=>BillingInvoice::class,
            'subject_id'=>$invoice->id,'ip_address'=>$request->ip(),
        ]);
        return back()->with('status','Invoice notification requested. Email delivery still follows Phase 5 recipient and test-mode rules.');
    }
}
