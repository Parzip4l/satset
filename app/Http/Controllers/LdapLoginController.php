<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Str;
use LdapRecord\Connection;
use LdapRecord\Auth\BindException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class LdapLoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $request->input('email');
        $password = $request->input('password');
        $upn = $email;

        // Setup koneksi LDAP
        $connection = new Connection([
            'hosts' => [env('LDAP_HOST')],
            'base_dn' => env('LDAP_BASE_DN'),
            'port' => env('LDAP_PORT', 389),
            'use_ssl' => env('LDAP_SSL', false),
            'use_tls' => env('LDAP_TLS', false),
        ]);

        try {
            $connection->connect();
            $connection->auth()->bind($upn, $password);

            // Jika bind berhasil, lanjutkan ambil info user dari LDAP
            $rawLdap = $connection->getLdapConnection()->getConnection();
            $dn = env('LDAP_BASE_DN');
            $filter = "(userPrincipalName={$upn})";
            $attributes = ['displayName', 'mail', 'department', 'distinguishedName','company','title'];

            $search = @ldap_search($rawLdap, $dn, $filter, $attributes);

            if (!$search) {
                return back()->withErrors(['ldap' => 'LDAP search error.']);
            }

            $entries = ldap_get_entries($rawLdap, $search);
            if ($entries['count'] === 0) {
                return back()->withErrors(['ldap' => 'Pengguna tidak ditemukan di LDAP.']);
            }

            $entry = $entries[0];

            // Buat atau ambil user lokal
            $user = User::firstOrCreate([
                'email' => $email,
            ], [
                'name'       => $entry['displayname'][0] ?? $email,
                'username'   => explode('@', $email)[0],
                'department' => $entry['department'][0] ?? null,
                'password'   => bcrypt(str()->random(12)),
                'phone'      => '0',
            ]);

            Auth::login($user);
            return redirect()->route('dashboard.index')->with('success', 'Berhasil login (LDAP) sebagai ' . $user->name);

        } catch (\LdapRecord\Auth\BindException $e) {
            // Jika LDAP gagal, coba login dari user lokal
            $user = User::where('email', $email)->first();

            if ($user && Hash::check($password, $user->password)) {
                Auth::login($user);
                return redirect()->route('dashboard.index')->with('success', 'Berhasil login (lokal) sebagai ' . $user->name);
            }

            // Jika user lokal tidak cocok, tampilkan error tapi jangan error DB
            return back()->withErrors([
                'login' => 'Gagal login: email atau password salah.',
            ]);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
