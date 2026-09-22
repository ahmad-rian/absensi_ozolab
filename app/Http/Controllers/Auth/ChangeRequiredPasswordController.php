<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ChangeRequiredPasswordController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('auth/ganti-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'confirmed', Password::defaults(), 'not_in:password,11111111']]);
        $request->user()->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false, 'remember_token' => null])->save();
        $request->session()->regenerate();

        return to_route($request->user()->homeRoute());
    }
}
