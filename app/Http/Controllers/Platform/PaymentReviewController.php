<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentMethod;
use App\Models\SubscriptionPaymentRequest;
use App\Services\SubscriptionPaymentApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReviewController extends Controller
{
    public function index(Request $request): View
    {
        $query = SubscriptionPaymentRequest::with(['tenant:id,name,slug', 'plan:id,name', 'submitter:id,name,email', 'reviewer:id,name'])
            ->latest('id');

        if (in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $request->query('status'));
        }
        if (in_array($request->query('method'), ['asset', 'card'], true)) {
            $query->where('method', $request->query('method'));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q->whereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.$search.'%'))
                ->orWhere('customer_reference', 'like', '%'.$search.'%')
                ->orWhere('review_reference', 'like', '%'.$search.'%'));
        }

        $payments = $query->paginate(20)->withQueryString();
        $stats = [
            'pending' => SubscriptionPaymentRequest::where('status', 'pending')->count(),
            'approved' => SubscriptionPaymentRequest::where('status', 'approved')->count(),
            'rejected' => SubscriptionPaymentRequest::where('status', 'rejected')->count(),
        ];

        return view('platform.payments.review-index', compact('payments', 'stats'));
    }

    public function show(SubscriptionPaymentRequest $payment): View
    {
        $payment->load(['tenant', 'plan', 'submitter', 'reviewer']);
        return view('platform.payments.review', compact('payment'));
    }

    public function approve(
        Request $request,
        SubscriptionPaymentRequest $payment,
        SubscriptionPaymentApproval $approval
    ): RedirectResponse {
        $data = $request->validate([
            'approved_amount' => ['required', 'numeric', 'min:0.01', 'max:10000000', 'decimal:0,2'],
            'review_reference' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'confirm_funds_checked' => ['required', 'accepted'],
        ]);

        $value = (string) $data['approved_amount'];
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $minor = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        $approval->approve(
            $payment, $request->user(), $minor, $data['review_reference'],
            $data['review_notes'] ?? null
        );

        return redirect()->route('platform.payments.review.show', $payment)
            ->with('status', 'Verified amount recorded and subscription activated. This is a manual reconciliation decision.');
    }

    public function reject(
        Request $request,
        SubscriptionPaymentRequest $payment,
        SubscriptionPaymentApproval $approval
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $approval->reject($payment, $request->user(), $data['reason']);

        return back()->with('status', 'The payment request was rejected. Subscription access was not changed.');
    }

    public function receipt(SubscriptionPaymentRequest $payment): StreamedResponse
    {
        abort_unless($payment->method === 'asset' && $payment->proof_path, 404);
        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download(
            $payment->proof_path,
            'payment-evidence-'.$payment->id.'.'.pathinfo($payment->proof_path, PATHINFO_EXTENSION),
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']
        );
    }
}
