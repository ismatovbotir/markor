<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('import-batches.index');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // brute force: 5 wrong passwords per email+IP per minute (plus a per-IP route limit)
        $key = 'login:' . strtolower($credentials['email']) . '|' . $request->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => __('auth.throttle', ['seconds' => \Illuminate\Support\Facades\RateLimiter::availableIn($key)])])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            \Illuminate\Support\Facades\RateLimiter::clear($key);
            $request->session()->regenerate();

            if (!Auth::user()->is_active) {
                Auth::logout();
                return back()->withErrors(['email' => __('auth.inactive')]);
            }

            return redirect()->intended(route(Auth::user()->isAdmin() ? 'admin.clients' : 'import-batches.index'));
        }

        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);

        return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
    }

    public function loginByQr(Request $request)
    {
        $token = trim($request->input('qr_token', ''));

        $user = strlen($token) >= 24
            ? User::where('qr_token', $token)->where('is_active', true)->where('role', '!=', 'admin')->first()
            : null;

        if (!$user) {
            return redirect()->route('login')->with('error', __('auth.failed'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('import-batches.index');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
