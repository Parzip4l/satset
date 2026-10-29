<?php

namespace App\Services\It;

use App\Models\It\SupportTeam;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FormEngine
{
    public function options(array $field, array $configuration): array
    {
        if ($field['type'] !== 'reference') {
            return $field['options'] ?? [];
        }
        $source = $field['source'] ?? '';
        if ($source === 'support_teams') {
            return SupportTeam::where('is_active', true)->orderBy('name')->get()->map(fn ($team) => ['value' => (string) $team->id, 'label' => $team->name])->all();
        }
        $section = ['it_services' => 'services', 'it_categories' => 'categories', 'locations' => 'locations'][$source] ?? null;

        return $section ? array_values(array_map(fn ($item) => ['value' => $item['key'], 'label' => $item['name']], array_filter($configuration[$section], fn ($item) => $item['is_active']))) : [];
    }

    public function evaluate(?array $node, array $values): bool
    {
        if (! $node) {
            return true;
        }
        if (isset($node['mode'])) {
            $checks = array_map(fn ($child) => $this->evaluate($child, $values), $node['rules']);

            return $node['mode'] === 'all' ? ! in_array(false, $checks, true) : in_array(true, $checks, true);
        }
        $actual = $values[$node['field']] ?? null;
        $expected = $node['value'] ?? null;
        $same = fn ($a, $b) => is_bool($a) || is_bool($b) ? $a === $b : (is_scalar($a) && is_scalar($b) && (string) $a === (string) $b);

        return match ($node['operator']) {
            'equals' => is_array($actual) ? count(array_filter($actual, fn ($value) => $same($value, $expected))) > 0 : $same($actual, $expected),
            'not_equals' => is_array($actual) ? count(array_filter($actual, fn ($value) => $same($value, $expected))) === 0 : ! $same($actual, $expected),
            'in' => count(array_filter((array) $actual, fn ($value) => count(array_filter($expected, fn ($option) => $same($value, $option))) > 0)) > 0,
            'is_empty' => $actual === null || $actual === '' || $actual === [],
            default => false,
        };
    }

    public function validateDefault(array $field, array $configuration, string $path): void
    {
        if ($field['type'] === 'file') {
            throw ValidationException::withMessages(['configuration.'.$path => 'File tidak dapat memiliki default.']);
        }
        $rules = $this->rules($field, $configuration, (bool) ($field['required'] ?? false));
        $validator = Validator::make(['value' => $field['default']], ['value' => $rules['value']] + $rules['children']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['configuration.'.$path.'.default' => 'Default tidak sesuai tipe, batas, atau pilihan field.']);
        }
    }

    /** Resolve dependencies first; hidden/read-only inputs are never trusted. No data or files are persisted. */
    public function inspect(array $configuration, string $formKey, string $role, array $answers = [], array $uploads = [], bool $validate = false): array
    {
        if (! in_array($role, ConfigurationSchema::ROLES, true)) {
            abort(422, 'Peran preview tidak dikenal.');
        }
        $form = collect($configuration['forms'])->firstWhere('key', $formKey);
        abort_unless($form, 404, 'Form tidak ditemukan pada versi ini.');
        $fields = array_column($form['fields'], null, 'key');
        $values = [];
        $visible = [];
        $resolving = [];
        $issues = [];
        foreach (array_diff(array_keys($answers + $uploads), array_keys($fields)) as $unknown) {
            $issues['answers.'.$unknown] = 'Field tidak dikenal atau dikendalikan sistem.';
        }
        $resolve = function (string $key) use (&$resolve, &$values, &$visible, &$resolving, &$issues, $fields, $role, $answers, $uploads, $configuration, $validate) {
            if (array_key_exists($key, $visible)) {
                return;
            }
            if (isset($resolving[$key])) {
                throw ValidationException::withMessages(['configuration' => 'Siklus kondisi tidak valid.']);
            }
            $resolving[$key] = true;
            $field = $fields[$key];
            foreach (['visible_when', 'required_when'] as $condition) {
                $walk = function ($node) use (&$walk, &$resolve) {
                    if (! $node) {
                        return;
                    }
                    if (isset($node['rules'])) {
                        foreach ($node['rules'] as $child) {
                            $walk($child);
                        }
                    } elseif (isset($node['field'])) {
                        $resolve($node['field']);
                    }
                };
                $walk($field[$condition] ?? null);
            }
            $shown = in_array($role, $field['visible_roles'], true) && $this->evaluate($field['visible_when'] ?? null, $values);
            $visible[$key] = $shown;
            $provided = array_key_exists($key, $answers) || array_key_exists($key, $uploads);
            $readOnly = in_array($role, $field['read_only_roles'] ?? [], true);
            if (! $shown || $readOnly) {
                if ($provided) {
                    $issues['answers.'.$key] = $shown ? 'Field read-only tidak boleh dikirim.' : 'Field tersembunyi tidak boleh dikirim.';
                }
                if ($shown && array_key_exists('default', $field)) {
                    $values[$key] = $field['default'];
                }
                unset($resolving[$key]);

                return;
            }
            $value = $field['type'] === 'file' ? ($uploads[$key] ?? null) : ($answers[$key] ?? ($field['default'] ?? null));
            if (array_key_exists($key, $uploads) && $field['type'] !== 'file') {
                $issues['answers.'.$key] = 'Upload hanya untuk field file.';
            }
            if (array_key_exists($key, $answers) && $field['type'] === 'file') {
                $issues['answers.'.$key] = 'File harus dikirim sebagai multipart upload.';
            }
            if ($value === '') {
                $value = null;
            }
            if ($field['type'] === 'checkbox' && in_array($value, [0, 1, '0', '1', false, true], true)) {
                $value = (bool) $value;
            }
            if (in_array($field['type'], ['integer', 'decimal'], true) && is_scalar($value) && is_numeric($value) && is_finite((float) $value) && abs((float) $value) <= 1000000000) {
                $value = $field['type'] === 'integer' && floor((float) $value) == (float) $value ? (int) $value : ($field['type'] === 'decimal' ? (float) $value : $value);
            }
            $required = ($field['required'] ?? false) || (! empty($field['required_when']) && $this->evaluate($field['required_when'], $values));
            $rules = $this->rules($field, $configuration, $required);
            $validator = Validator::make(['value' => $value], ['value' => $rules['value']] + $rules['children'], [
                'required' => ':attribute wajib diisi.', 'accepted' => ':attribute harus disetujui.',
                'string' => ':attribute harus berupa teks.', 'integer' => ':attribute harus berupa angka bulat.',
                'numeric' => ':attribute harus berupa angka.', 'boolean' => ':attribute harus berupa pilihan ya/tidak.',
                'in' => 'Pilihan :attribute tidak tersedia.', 'array' => 'Format pilihan/file :attribute tidak valid.',
                'distinct' => 'Pilihan tidak boleh duplikat.', 'date_format' => 'Format tanggal/waktu :attribute tidak valid.',
                'email' => 'Alamat email tidak valid.', 'url' => 'URL harus menggunakan http atau https.',
                'regex' => 'Format :attribute tidak valid.', 'between' => ':attribute berada di luar batas yang diizinkan.',
                'min' => ':attribute belum memenuhi batas minimum :min.', 'max' => ':attribute melebihi batas :max.',
                'file' => 'Upload harus berupa file.', 'mimes' => 'Jenis file tidak diizinkan.',
            ], ['value' => $field['label'], 'value.*' => $field['label']]);
            if ($validator->fails()) {
                // Never let invalid values drive conditions of dependent fields.
                if ($validate || ($provided && $value !== null)) {
                    $issues['answers.'.$key] = $validator->errors()->first();
                }
            } else {
                $values[$key] = $value;
            }
            unset($resolving[$key]);
        };
        foreach (array_keys($fields) as $key) {
            $resolve($key);
        }
        $rendered = [];
        foreach ($fields as $key => $field) {
            if (! ($visible[$key] ?? false)) {
                continue;
            }
            // A projection for this role; exclude condition trees and hidden defaults.
            $rendered[] = array_intersect_key($field, array_flip(['key', 'label', 'type', 'help', 'placeholder', 'section', 'width', 'min', 'max', 'min_length', 'max_length', 'max_files', 'max_size_kb', 'allowed_mimes', 'format'])) + [
                'options' => $this->options($field, $configuration), 'value' => $values[$key] ?? null,
                'required' => ($field['required'] ?? false) || (! empty($field['required_when']) && $this->evaluate($field['required_when'], $values)),
                'read_only' => in_array($role, $field['read_only_roles'] ?? [], true),
            ];
        }
        $normalized = [];
        foreach ($values as $key => $value) {
            $normalized[$key] = $fields[$key]['type'] === 'file' && is_array($value)
                ? array_map(fn (UploadedFile $file) => ['name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(), 'size' => $file->getSize()], $value) : $value;
        }
        // Do not echo file objects into the role-projected schema.
        foreach ($rendered as &$field) {
            if ($field['type'] === 'file') {
                $field['value'] = null;
            }
        }

        return ['form_key' => $formKey, 'role' => $role, 'fields' => $rendered, 'answers' => $normalized, 'errors' => $issues, 'valid' => $validate ? ! count($issues) : null, 'persisted' => false];
    }

    private function rules(array $field, array $configuration, bool $required): array
    {
        $base = [$required ? 'required' : 'nullable'];
        $children = [];
        switch ($field['type']) {
            case 'text': case 'textarea':
                $base[] = 'string';
                $base[] = 'max:'.($field['max_length'] ?? 10000);
                if (isset($field['min_length'])) {
                    $base[] = 'min:'.$field['min_length'];
                }
                if (($field['format'] ?? null) === 'email') {
                    $base[] = 'email:rfc';
                }
                if (($field['format'] ?? null) === 'url') {
                    $base[] = 'url:http,https';
                }
                if (($field['format'] ?? null) === 'phone') {
                    $base[] = 'regex:/^\+?[0-9 ()-]{6,30}$/';
                }
                break;
            case 'integer': case 'decimal':
                $base[] = $field['type'] === 'integer' ? 'integer' : 'numeric';
                $base[] = 'between:-1000000000,1000000000';
                foreach (['min', 'max'] as $bound) {
                    if (isset($field[$bound])) {
                        $base[] = $bound.':'.$field[$bound];
                    }
                }
                break;
            case 'date': $base[] = 'date_format:Y-m-d';
                break;
            case 'datetime': $base[] = 'date_format:Y-m-d\TH:i';
                break;
            case 'time': $base[] = 'date_format:H:i';
                break;
            case 'checkbox': $base[] = 'boolean';
                if ($required) {
                    $base[] = 'accepted';
                } break;
            case 'select': case 'radio': case 'reference':
                $base[] = 'string';
                $base[] = Rule::in(array_column($this->options($field, $configuration), 'value'));
                break;
            case 'multiselect':
                $base[] = 'array';
                $base[] = 'max:100';
                $children['value.*'] = ['string', 'distinct', Rule::in(array_column($this->options($field, $configuration), 'value'))];
                break;
            case 'file':
                $base[] = 'array';
                $base[] = 'max:'.($field['max_files'] ?? 1);
                $children['value.*'] = ['required', 'file', 'max:'.($field['max_size_kb'] ?? 5120), 'mimes:'.implode(',', $field['allowed_mimes'] ?? ['pdf', 'jpg', 'jpeg', 'png'])];
                break;
        }

        return ['value' => $base, 'children' => $children];
    }
}
