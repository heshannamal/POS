<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PaymentsController extends Controller
{
    public function index(): View
    {
        // Financial figures are intentionally absent until an approved gateway
        // and reconciled ledger exist. Never synthesize merchant balances here.
        return view('platform.payments.index');
    }
}
