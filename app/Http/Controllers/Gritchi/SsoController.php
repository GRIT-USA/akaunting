<?php

namespace App\Http\Controllers\Gritchi;

use App\Abstracts\Http\Controller;
use App\Models\Auth\User;
use App\Models\Common\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    public function consume(Request $request)
    {
        $payload = $this->verifyToken($request->query('token'));

        if (($payload['aud'] ?? null) !== 'akaunting') {
            abort(403);
        }

        $email = strtolower(trim($payload['email'] ?? ''));

        if ($email === '') {
            abort(403);
        }

        $user = User::withTrashed()->where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => trim(($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? '')) ?: $email,
                'email' => $email,
                'password' => Str::random(40),
                'locale' => env('GRITCHI_SSO_LOCALE', config('app.locale')),
                'enabled' => true,
                'landing_page' => 'dashboard',
                'created_from' => 'gritchi-sso',
            ]);
        }

        if ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill(['enabled' => true])->save();

        $company = $this->ensureCompany($user);
        $this->ensureAdminAccess($user, $company);

        auth()->login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard', ['company_id' => $company->id]);
    }

    private function verifyToken(?string $token): array
    {
        if (! $token) {
            abort(403);
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            abort(403);
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;
        $secret = env('GRITCHI_SSO_SECRET');

        if (! $secret) {
            abort(403);
        }

        $expected = $this->base64UrlEncode(hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $secret, true));

        if (! hash_equals($expected, $signature)) {
            abort(403);
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (! is_array($payload)) {
            abort(403);
        }

        if (($payload['iss'] ?? null) !== 'gritchi-portal') {
            abort(403);
        }

        if (($payload['exp'] ?? 0) < time()) {
            abort(403);
        }

        return $payload;
    }

    private function ensureCompany(User $user): Company
    {
        $company = $user->withoutEvents(fn () => $user->companies()->enabled()->first());

        if ($company) {
            return $company;
        }

        $companyId = (int) env('AKAUNTING_COMPANY_ID', 0);

        $company = $companyId > 0
            ? Company::enabled()->find($companyId)
            : Company::enabled()->orderBy('id')->first();

        if (! $company) {
            abort(403);
        }

        DB::table('user_companies')->updateOrInsert([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        return $company;
    }

    private function ensureAdminAccess(User $user, Company $company): void
    {
        $role = role_model_class()::where('name', env('GRITCHI_SSO_ROLE', 'admin'))->first();

        if ($role) {
            $roleKeys = [
                'user_id' => $user->id,
                'role_id' => $role->id,
            ];

            if (Schema::hasColumn('user_roles', 'user_type')) {
                $roleKeys['user_type'] = User::class;
            }

            DB::table('user_roles')->updateOrInsert($roleKeys);
        }

        if (! $user->dashboards()->exists()) {
            Artisan::call('user:seed', [
                'user' => $user->id,
                'company' => $company->id,
            ]);
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
