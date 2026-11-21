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

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            if (!Auth::user()->is_active) {
                Auth::logout();
                return back()->withErrors(['email' => __('auth.inactive')]);
            }

            return redirect()->intended(route(Auth::user()->isAdmin() ? 'admin.clients' : 'import-batches.index'));
        }

        return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
    }

    public function loginByQr(Request $request)
    {
        $token = trim($request->input('qr_token', ''));

        $user = User::where('qr_token', $token)->where('is_active', true)->first();

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
