<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitialRegistrationRequest;
use App\Services\InitialRegistrationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InitialRegistrationController extends Controller
{
    public function create(InitialRegistrationService $registration): View
    {
        abort_unless($registration->isOpen(), 404);

        return view('admin.auth.register', ['title' => 'Set up your church', 'timezones' => timezone_identifiers_list()]);
    }

    public function store(InitialRegistrationRequest $request, InitialRegistrationService $registration): RedirectResponse
    {
        abort_unless($registration->isOpen(), 404);

        try {
            $user = $registration->register($request->validated());
        } catch (DomainException) {
            abort(404);
        }

        backpack_auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')->with('success', 'Church setup complete. You are signed in as the App Administrator.');
    }
}
