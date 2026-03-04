<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required']);

        if ($request->input('password') !== config('app.dashboard_password')) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $request->session()->put('dashboard_authed', true);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('dashboard_authed');

        return redirect()->route('login');
    }
}
