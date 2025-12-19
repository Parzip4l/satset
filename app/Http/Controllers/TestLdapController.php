<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Auth\ListensForLdapBindFailure;
use LdapRecord\Container;
use LdapRecord\Connection;

class TestLdapController extends Controller
{
    use ListensForLdapBindFailure;

    public function showLoginForm()
    {
        return view('ldap.login');
    }

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
            'hosts' => [env('LDAP_HOST')],
            'base_dn' => env('LDAP_BASE_DN'),
            'port' => env('LDAP_PORT', 389),
            'use_ssl' => env('LDAP_SSL', false),
            'use_tls' => env('LDAP_TLS', false),
        ]);

        try {
            $connection->connect();
            $connection->auth()->bind($upn, $password);

            $rawLdap = $connection->getLdapConnection()->getConnection(); // ambil resource asli

            $dn = env('LDAP_BASE_DN');
            $filter = "(userPrincipalName=$upn)";
            $attributes = ['displayName', 'mail', 'sAMAccountName','department']; // bisa tambah sesuai kebutuhan

            $search = ldap_search($rawLdap, $dn, $filter, $attributes);
            $entries = ldap_get_entries($rawLdap, $search);
            return back()->with('success', '✅ Login LDAP berhasil sebagai: ' . $upn);
        } catch (\LdapRecord\Auth\BindException $e) {
            $error = $e->getDetailedError();
            return back()->withErrors([
                'ldap' => "❌ Login gagal: {$error->getErrorCode()} - {$error->getDiagnosticMessage()}",
            ]);
        }
    }
}
