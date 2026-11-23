<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\BillingRefund;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\BillingPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $invoices = $tenant->invoices()->withSum('refunds','amount_minor')
            ->latest('id')->paginate(15);
        $subscription = $tenant->subscription;
        return view('business.billing.index',compact('tenant','invoices','subscription'));
    }

    public function show(Tenant $tenant, BillingInvoice $invoice): View
    {
        abort_unless($invoice->tenant_id === $tenant->id,404);
        $invoice->load(['refunds','payment.reviewer']);
        return view('business.billing.show',compact('tenant','invoice'));
    }

    public function pdf(Tenant $tenant, BillingInvoice $invoice, BillingPdf $pdf): Response
    {
        abort_unless($invoice->tenant_id === $tenant->id,404);
        $type = request()->query('type','invoice');
        abort_unless(in_array($type,['invoice','receipt'],true),404);
        abort_if($type==='receipt' && $invoice->status!=='paid',404);
        return response($pdf->invoice($invoice,$type==='receipt'),200,[
            'Content-Type'=>'application/pdf',
            'Content-Disposition'=>'attachment; filename="'.($type==='receipt'?$invoice->receipt_number:$invoice->number).'.pdf"',
            'Cache-Control'=>'private, no-store',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }

    public function creditNote(Tenant $tenant, BillingRefund $refund, BillingPdf $pdf): Response
    {
        abort_unless($refund->tenant_id===$tenant->id,404);
        return response($pdf->creditNote($refund),200,[
            'Content-Type'=>'application/pdf',
            'Content-Disposition'=>'attachment; filename="'.$refund->credit_note_number.'.pdf"',
            'Cache-Control'=>'private, no-store',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }

    public function renewal(Request $request, Tenant $tenant): RedirectResponse
    {
        $data=$request->validate([
            'auto_renew'=>['required','boolean'],
            'cancel_at_period_end'=>['required','boolean'],
        ]);
        DB::transaction(function () use ($request,$tenant,$data): void {
            $subscription=Subscription::where('tenant_id',$tenant->id)->lockForUpdate()->firstOrFail();
            $subscription->update([
                'auto_renew'=>(bool)$data['auto_renew'],
                'cancel_at_period_end'=>$data['cancel_at_period_end'] ? $subscription->ends_at : null,
            ]);
            AuditLog::create([
                'tenant_id'=>$tenant->id,'actor_id'=>$request->user()->id,
                'action'=>'billing.renewal.preferences','subject_type'=>Subscription::class,
                'subject_id'=>$subscription->id,'metadata'=>[
                    'auto_renew'=>(bool)$data['auto_renew'],
                    'cancel_at_period_end'=>(bool)$data['cancel_at_period_end'],
                    'automatic_charge_executed'=>false,
                ],'ip_address'=>$request->ip(),
            ]);
        });
        return back()->with('status','Renewal preference saved. Automatic card charging is not connected; renewal still requires a verified payment or manual admin action.');
    }
}
