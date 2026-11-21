<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/** Self-registration for clients. New accounts get the `user` role; the super admin can deactivate them. */
class RegisterController extends Controller
{
    public function show()
    {
        return Auth::check() ? redirect()->route('import-batches.index') : view('auth.register');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'company'  => 'nullable|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'required|string|max:30',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'      => trim($data['name'] . (filled($data['company'] ?? null) ? ' — ' . $data['company'] : '')),
            'email'     => strtolower($data['email']),
            'phone'     => $data['phone'],
            'password'  => $data['password'],
            'role'      => 'user',
            'locale'    => app()->getLocale(),
            'qr_token'  => Str::random(32),
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        app(\App\Domain\Telegram\Notifier::class)->userRegistered($user); // Telegram alert to the super admin

        return redirect()->route('import-batches.index');
    }
}
