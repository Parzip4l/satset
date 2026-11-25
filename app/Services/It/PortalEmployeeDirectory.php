<?php

namespace App\Services\It;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PortalEmployeeDirectory
{
    public function search(string $search, int $page = 1): array
    {
        return $this->request(['search' => $search, 'page' => $page]);
    }

    public function find(string $portalId): array
    {
        $response = $this->request(['employee_id' => $portalId]);
        $employee = collect($response['employees'])->first(fn ($row) => strcasecmp($row['id'], $portalId) === 0);
        if (! $employee || $employee['status'] !== 'active') {
            throw ValidationException::withMessages(['assigned_employee_portal_id' => 'Karyawan tidak ditemukan atau sudah tidak aktif di Portal. Pilih karyawan aktif.']);
        }

        return $employee;
    }

    private function request(array $input): array
    {
        $secret = (string) config('satset.employee_directory.shared_secret');
        if ($secret === '') {
            $this->fail('Koneksi direktori karyawan Portal belum dikonfigurasi. Hubungi administrator.');
        }
        $body = json_encode(['purpose' => 'it_asset_assignment', 'per_page' => 20] + $input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $endpoint = rtrim((string) config('satset.employee_directory.base_url'), '/').'/'.ltrim((string) config('satset.employee_directory.endpoint'), '/');
        try {
            $response = Http::acceptJson()->withHeaders([
                'X-App-Code' => 'satset', 'X-Satset-Timestamp' => $timestamp,
                'X-Satset-Signature' => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
            ])->timeout((int) config('satset.employee_directory.timeout', 8))
                ->withOptions(['verify' => (bool) config('satset.employee_directory.verify_ssl', true)])
                ->withBody($body, 'application/json')->post($endpoint);
        } catch (ConnectionException) {
            $this->fail('Direktori karyawan Portal sedang tidak dapat dihubungi. Coba lagi.');
        }
        if (! $response->successful()) {
            $this->fail('Direktori karyawan Portal belum tersedia atau koneksinya ditolak. Hubungi administrator.');
        }
        $json = $response->json();
        $validator = Validator::make(is_array($json) ? $json : [], [
            'success' => 'required|accepted', 'data' => 'present|array|max:50',
            'data.*.id' => 'required|uuid|distinct', 'data.*.name' => 'required|string|max:255',
            'data.*.email' => 'required|email|max:255', 'data.*.status' => 'required|string|max:30',
            'data.*.employee_number' => 'nullable|string|max:100', 'data.*.department' => 'nullable|string|max:255',
            'meta.pagination.current_page' => 'required|integer|min:1',
            'meta.pagination.has_more' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            $this->fail('Data direktori karyawan dari Portal belum valid. Hubungi administrator.');
        }
        $data = $validator->validated();

        return ['employees' => array_map(fn ($row) => array_intersect_key($row, array_flip(['id', 'name', 'email', 'status', 'employee_number', 'department'])), $data['data']), 'page' => $data['meta']['pagination']['current_page'], 'has_more' => (bool) $data['meta']['pagination']['has_more']];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['employee_directory' => $message]);
    }
}
