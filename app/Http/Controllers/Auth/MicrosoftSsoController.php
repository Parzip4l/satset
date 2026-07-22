<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MicrosoftSsoController extends Controller
{
    public function redirect(Request $request)
    {
        $this->ensureConfigured();

        $state = Str::random(40);
        $request->session()->put('microsoft_sso_state', $state);

        $tenantId = config('services.microsoft_sso.tenant_id');

        $parameters = [
            'client_id' => config('services.microsoft_sso.client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'response_mode' => 'query',
            'scope' => 'openid profile email User.Read',
            'state' => $state,
        ];

        if (filled(config('services.microsoft_sso.domain_hint'))) {
            $parameters['domain_hint'] = config('services.microsoft_sso.domain_hint');
        }

        if (filled(config('services.microsoft_sso.prompt'))) {
            $parameters['prompt'] = config('services.microsoft_sso.prompt');
        }

        if ($request->filled('login_hint')) {
            $parameters['login_hint'] = $request->query('login_hint');
        }

        $query = http_build_query($parameters);

        return redirect("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize?{$query}");
    }

    public function callback(Request $request)
    {
        $this->ensureConfigured();

        if ($request->filled('error')) {
            Log::warning('Microsoft SSO returned an error.', [
                'error' => $request->query('error'),
                'description' => $request->query('error_description'),
            ]);

            return redirect()->route('login')->withErrors([
                'microsoft' => 'Login Microsoft gagal: ' . $request->query('error_description', $request->query('error')),
            ]);
        }

        if (! hash_equals((string) $request->session()->pull('microsoft_sso_state'), (string) $request->query('state'))) {
            return redirect()->route('login')->withErrors([
                'microsoft' => 'Sesi login Microsoft tidak valid. Silakan coba lagi.',
            ]);
        }

        if (! $request->filled('code')) {
            return redirect()->route('login')->withErrors([
                'microsoft' => 'Kode otorisasi Microsoft tidak ditemukan.',
            ]);
        }

        try {
            $token = $this->exchangeCodeForToken($request->query('code'));
            $profile = $this->fetchProfile($token['access_token']);
            $user = $this->findOrCreateUser($profile);

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard.index'))
                ->with('success', 'Berhasil login dengan Microsoft sebagai ' . $user->name);
        } catch (\Throwable $e) {
            Log::error('Microsoft SSO login failed.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')->withErrors([
                'microsoft' => 'Login Microsoft belum berhasil. Silakan hubungi admin IT.',
            ]);
        }
    }

    private function exchangeCodeForToken(string $code): array
    {
        $tenantId = config('services.microsoft_sso.tenant_id');

        $response = Http::asForm()
            ->timeout(20)
            ->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'client_id' => config('services.microsoft_sso.client_id'),
                'client_secret' => config('services.microsoft_sso.client_secret'),
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
                'scope' => 'openid profile email User.Read',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Microsoft token request failed: ' . $response->body());
        }

        return $response->json();
    }

    private function fetchProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(20)
            ->get('https://graph.microsoft.com/v1.0/me', [
                '$select' => 'id,displayName,mail,userPrincipalName,employeeId,jobTitle,department,mobilePhone,businessPhones',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Microsoft profile request failed: ' . $response->body());
        }

        return $response->json();
    }

    private function findOrCreateUser(array $profile): User
    {
        $email = Str::lower($profile['mail'] ?? $profile['userPrincipalName'] ?? '');

        if ($email === '') {
            throw new \RuntimeException('Microsoft profile does not contain an email address.');
        }

        $allowedDomain = config('services.microsoft_sso.allowed_domain');
        if ($allowedDomain && ! Str::endsWith($email, '@' . ltrim($allowedDomain, '@'))) {
            throw new \RuntimeException("Email {$email} is outside the allowed Microsoft domain.");
        }

        $user = User::firstOrNew(['email' => $email]);
        $username = Str::before($email, '@');

        $this->setColumn($user, 'name', $profile['displayName'] ?? $user->name ?? $username);
        $this->setColumn($user, 'email', $email);
        $this->setColumn($user, 'username', $user->username ?: $username);
        $this->setColumn($user, 'password', $user->password ?: Hash::make(Str::random(32)));
        $this->setColumn($user, 'phone', $profile['mobilePhone'] ?? data_get($profile, 'businessPhones.0') ?? $user->phone ?? '0');
        $this->setColumn($user, 'nik', $profile['employeeId'] ?? $user->nik);
        $this->setColumn($user, 'role', $user->role ?: 'pelapor');
        $this->setColumn($user, 'user_type', $user->user_type ?: 'karyawan');
        $this->setColumn($user, 'kartu_uang_1', $user->kartu_uang_1 ?: '-');

        if (Schema::hasColumn('users', 'email_verified_at') && ! $user->email_verified_at) {
            $user->email_verified_at = now();
        }

        $user->save();

        return $user;
    }

    private function setColumn(User $user, string $column, mixed $value): void
    {
        if (Schema::hasColumn('users', $column) && $value !== null) {
            $user->{$column} = $value;
        }
    }

    private function redirectUri(): string
    {
        return config('services.microsoft_sso.redirect_uri') ?: route('auth.microsoft.callback');
    }

    private function ensureConfigured(): void
    {
        abort_unless(config('services.microsoft_sso.enabled'), 404);

        foreach (['tenant_id', 'client_id', 'client_secret'] as $key) {
            if (blank(config("services.microsoft_sso.{$key}"))) {
                throw new \RuntimeException("Microsoft SSO config {$key} belum diisi.");
            }
        }
    }
}
