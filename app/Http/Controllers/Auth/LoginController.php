<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Route;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    protected function redirectTo(): string
    {
        $role = strtolower((string) auth()->user()?->roles()->value('roles.name'));

        return match ($role) {
            'admin' => route('admin.users.index'),
            'client' => route('client.dashboard'),
            'operator' => Route::has('operator.dashboard') ? route('operator.dashboard') : '/operator',
            default => '/home',
        };
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }
}
