<?php

namespace App\Http\Controllers;

use App\Services\CreateTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function create(): View
    {
        return view('onboarding.create');
    }

    public function store(Request $request, CreateTenant $service): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenant = $service->handle($request->user(), $input);

        return redirect()->route('business.dashboard', $tenant)
            ->with('status', 'Your business is ready. Your trial has started.');
    }
}
