<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use App\Models\User;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');
        $domain = 'office.lrtjakarta.co.id';
        $upn = $username . '@' . $domain;

        $connection = new Connection([
            'hosts'    => [env('LDAP_HOST')],
            'base_dn'  => env('LDAP_BASE_DN'),
            'port'     => env('LDAP_PORT', 389),
            'use_ssl'  => env('LDAP_SSL', false),
            'use_tls'  => env('LDAP_TLS', false),
        ]);

        try {
            $connection->connect();
            $connection->auth()->bind($upn, $password);

            // Buat atau ambil user dari database Laravel
            $user = User::firstOrCreate(
                ['username' => $username],
                [
                    'name'     => $username,
                    'email'    => $username . '@lrtjakarta.co.id',
                    'phone'    => '0',
                    'password' => bcrypt(Str::random(10)), // acak agar tidak bisa login via local
                ]
            );

            Auth::login($user);

            return redirect()->route('dashboard.index')->with('success', 'Login berhasil sebagai ' . $username);
        } catch (\LdapRecord\Auth\BindException $e) {
            $error = $e->getDetailedError();
            return back()->withErrors([
                'ldap' => "❌ Login gagal: {$error->getErrorCode()} - {$error->getDiagnosticMessage()}",
            ]);
        }
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('login');
    }
}
