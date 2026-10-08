<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use LdapRecord\Auth\BindException;
use LdapRecord\Connection;
use Throwable;

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

        $connectionName = (string) config('ldap.default', 'default');
        $ldapConfig = (array) config("ldap.connections.{$connectionName}", []);
        $hosts = array_values(array_filter(
            (array) ($ldapConfig['hosts'] ?? []),
            fn ($host) => is_string($host) && trim($host) !== ''
        ));
        $baseDn = trim((string) ($ldapConfig['base_dn'] ?? ''));

        if ($hosts === [] || $baseDn === '') {
            Log::error('Konfigurasi LDAP login belum lengkap.', [
                'connection' => $connectionName,
                'has_host' => $hosts !== [],
                'has_base_dn' => $baseDn !== '',
            ]);

            return $this->attemptLocalLogin(
                $email,
                $password,
                'Konfigurasi LDAP pada server belum lengkap. Silakan hubungi administrator.'
            );
        }

        try {
            $connection = new Connection([
                'hosts' => $hosts,
                'base_dn' => $baseDn,
                'port' => (int) ($ldapConfig['port'] ?? 389),
                'timeout' => (int) ($ldapConfig['timeout'] ?? 5),
                'use_ssl' => (bool) ($ldapConfig['use_ssl'] ?? false),
                'use_tls' => (bool) ($ldapConfig['use_tls'] ?? false),
                'version' => (int) ($ldapConfig['version'] ?? 3),
            ]);
            $connection->connect();
            $connection->auth()->bind($upn, $password);

            // Jika bind berhasil, lanjutkan ambil info user dari LDAP
            $rawLdap = $connection->getLdapConnection()->getConnection();
            $dn = $baseDn;
            $escapedUpn = function_exists('ldap_escape')
                ? ldap_escape($upn, '', LDAP_ESCAPE_FILTER)
                : addcslashes($upn, '\\()*\x00');
            $filter = "(userPrincipalName={$escapedUpn})";
            $attributes = ['displayName', 'mail', 'department', 'distinguishedName', 'company', 'title'];

            $search = @ldap_search($rawLdap, $dn, $filter, $attributes);

            if (! $search) {
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
                'name' => $entry['displayname'][0] ?? $email,
                'username' => explode('@', $email)[0],
                'department' => $entry['department'][0] ?? null,
                'password' => bcrypt(str()->random(12)),
                'phone' => '0',
            ]);

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('dashboard.index')->with('success', 'Berhasil login (LDAP) sebagai '.$user->name);

        } catch (BindException) {
            return $this->attemptLocalLogin($email, $password, 'Gagal login: email atau password salah.');
        } catch (Throwable $exception) {
            Log::error('Koneksi LDAP login gagal.', [
                'connection' => $connectionName,
                'error' => $exception->getMessage(),
            ]);

            return $this->attemptLocalLogin(
                $email,
                $password,
                'Server LDAP sedang tidak dapat dihubungi. Silakan coba kembali atau hubungi administrator.'
            );
        }
    }

    private function attemptLocalLogin(string $email, string $password, string $failureMessage)
    {
        $user = User::where('email', $email)->first();

        if ($user && Hash::check($password, $user->password)) {
            Auth::login($user);
            request()->session()->regenerate();

            return redirect()->route('dashboard.index')->with('success', 'Berhasil login (lokal) sebagai '.$user->name);
        }

        return back()->withErrors(['login' => $failureMessage])->withInput(['email' => $email]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
