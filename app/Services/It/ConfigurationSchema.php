<?php

namespace App\Services\It;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConfigurationSchema
{
    public const ROLES = ['requester', 'technician', 'approver'];

    public const TYPES = ['text', 'textarea', 'integer', 'decimal', 'date', 'datetime', 'time', 'select', 'multiselect', 'radio', 'checkbox', 'file', 'reference'];

    public const SOURCES = ['it_services', 'it_categories', 'locations', 'support_teams'];

    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'txt', 'csv', 'docx', 'xlsx', 'pptx'];

    public const PROFILES = ['workflow' => 'phase3_pending', 'routing' => 'phase3_pending', 'approval' => 'phase3_pending', 'sla' => 'phase4_pending', 'submission_enabled' => false];

    public const RESERVED = ['id', 'ticket_no', 'submission_token', 'request_type', 'category_id', 'subcategory_id', 'department_id', 'impact_id', 'urgency_id', 'configuration_set_id', 'lock_version', 'password', 'passwd', 'access_token', 'refresh_token', 'api_key', 'private_key', 'client_secret', 'secret', 'requester_id', 'requested_for_id', 'service_domain_id', 'record_type', 'status', 'status_id', 'priority_id', 'assigned_user_id', 'assigned_department_id', 'payload', 'created_at', 'updated_at', 'configuration_version_id'];

    public function validate(array $config, bool $publishing = false): array
    {
        $key = ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,49}$/'];
        $rules = [
            'schema_version' => 'required|integer|in:1,2,3',
            'services' => 'present|array|max:100', 'categories' => 'present|array|max:200',
            'catalog' => 'present|array|max:200', 'forms' => 'present|array|max:100', 'locations' => 'present|array|max:200',
            'profiles' => 'required|array',
            'services.*' => 'array:key,name,description,owner_id,fallback_team_id,is_active',
            'categories.*' => 'array:key,name,parent_key,is_active',
            'locations.*' => 'array:key,name,parent_key,is_active',
            'catalog.*' => 'array:key,name,description,service_key,category_key,form_key,record_type,is_active,is_restricted',
            'forms.*' => 'array:key,name,record_type,fields',
            'services.*.owner_id' => 'nullable|integer|exists:users,id',
            'services.*.fallback_team_id' => 'nullable|integer|exists:support_teams,id',
            'forms.*.record_type' => 'required|in:incident,service_request',
            'forms.*.fields' => 'present|array|max:60',
            'catalog.*.record_type' => 'required|in:incident,service_request',
            'catalog.*.is_restricted' => 'sometimes|boolean',
            'catalog.*.service_key' => $key, 'catalog.*.category_key' => $key, 'catalog.*.form_key' => $key,
            'categories.*.parent_key' => 'nullable|string|max:50', 'locations.*.parent_key' => 'nullable|string|max:50',
        ];
        foreach (['services', 'categories', 'catalog', 'forms', 'locations'] as $section) {
            $rules[$section.'.*.key'] = $key;
            $rules[$section.'.*.name'] = 'required|string|max:150';
            if ($section !== 'forms') {
                $rules[$section.'.*.is_active'] = 'required|boolean';
            }
        }
        foreach (['services', 'catalog'] as $section) {
            $rules[$section.'.*.description'] = 'nullable|string|max:2000';
        }
        $fieldRoot = 'forms.*.fields.*';
        $rules[$fieldRoot] = 'array:key,label,type,help,placeholder,section,width,required,default,visible_roles,read_only_roles,visible_when,required_when,options,source,min,max,min_length,max_length,format,max_files,max_size_kb,allowed_mimes,reportable';
        $rules[$fieldRoot.'.key'] = $key;
        $rules[$fieldRoot.'.label'] = 'required|string|max:150';
        $rules[$fieldRoot.'.type'] = 'required|in:'.implode(',', self::TYPES);
        foreach (['help' => 1000, 'placeholder' => 255, 'section' => 100] as $name => $length) {
            $rules[$fieldRoot.'.'.$name] = 'nullable|string|max:'.$length;
        }
        foreach (['required', 'reportable'] as $name) {
            $rules[$fieldRoot.'.'.$name] = 'sometimes|boolean';
        }
        $rules[$fieldRoot.'.width'] = 'sometimes|in:6,12';
        foreach (['visible_roles', 'read_only_roles'] as $name) {
            $rules[$fieldRoot.'.'.$name] = ($name === 'visible_roles' ? 'required' : 'sometimes').'|array|'.($name === 'visible_roles' ? 'min:1|' : '').'max:3';
            $rules[$fieldRoot.'.'.$name.'.*'] = 'required|in:'.implode(',', self::ROLES);
        }
        $rules[$fieldRoot.'.options'] = 'sometimes|array|max:100';
        $rules[$fieldRoot.'.options.*'] = 'array:value,label';
        $rules[$fieldRoot.'.options.*.value'] = ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/'];
        $rules[$fieldRoot.'.options.*.label'] = 'required|string|max:150';
        $rules[$fieldRoot.'.source'] = 'nullable|in:'.implode(',', self::SOURCES);
        $rules[$fieldRoot.'.format'] = 'nullable|in:email,url,phone';
        $rules[$fieldRoot.'.visible_when'] = 'nullable|array';
        $rules[$fieldRoot.'.required_when'] = 'nullable|array';
        foreach (['min', 'max'] as $name) {
            $rules[$fieldRoot.'.'.$name] = 'nullable|numeric|between:-1000000000,1000000000';
        }
        foreach (['min_length', 'max_length'] as $name) {
            $rules[$fieldRoot.'.'.$name] = 'nullable|integer|between:0,10000';
        }
        $rules[$fieldRoot.'.max_files'] = 'nullable|integer|between:1,5';
        $rules[$fieldRoot.'.max_size_kb'] = 'nullable|integer|between:1,5120';
        $rules[$fieldRoot.'.allowed_mimes'] = 'sometimes|array|min:1|max:10';
        $rules[$fieldRoot.'.allowed_mimes.*'] = 'in:'.implode(',', self::MIMES);
        $topKeys = ['schema_version', 'services', 'categories', 'catalog', 'forms', 'locations', 'profiles'];
        if (array_diff(array_keys($config), $topKeys)) {
            $this->fail('configuration', 'Bagian konfigurasi tidak dikenal.');
        }
        Validator::make($config, $rules, [
            'required' => ':attribute wajib diisi.', 'present' => ':attribute harus tersedia.',
            'array' => 'Struktur :attribute tidak sesuai.', 'string' => ':attribute harus berupa teks.',
            'integer' => ':attribute harus berupa angka bulat.', 'numeric' => ':attribute harus berupa angka.',
            'boolean' => ':attribute harus berupa pilihan ya/tidak.', 'exists' => 'Referensi :attribute tidak ditemukan.',
            'in' => 'Pilihan :attribute tidak didukung.', 'regex' => 'Format kode :attribute tidak valid.',
            'max' => ':attribute melebihi batas :max.', 'min' => ':attribute belum memenuhi batas minimum :min.',
            'between' => ':attribute berada di luar batas yang diizinkan.',
        ])->validate();
        $config['schema_version'] = (int) $config['schema_version'];
        foreach (['services', 'categories', 'catalog', 'locations'] as $section) {
            foreach ($config[$section] as &$item) {
                $item['is_active'] = (bool) $item['is_active'];
                if (isset($item['is_restricted'])) {
                    $item['is_restricted'] = (bool) $item['is_restricted'];
                }
                foreach (['owner_id', 'fallback_team_id'] as $id) {
                    if (isset($item[$id])) {
                        $item[$id] = (int) $item[$id];
                    }
                }
            }
            unset($item);
        }
        foreach ($config['forms'] as &$form) {
            foreach ($form['fields'] as &$field) {
                foreach (['required', 'reportable'] as $boolean) {
                    if (isset($field[$boolean])) {
                        $field[$boolean] = (bool) $field[$boolean];
                    }
                }
                foreach (['width', 'min_length', 'max_length', 'max_files', 'max_size_kb'] as $integer) {
                    if (isset($field[$integer])) {
                        $field[$integer] = (int) $field[$integer];
                    }
                }
            }
            unset($field);
        }
        unset($form);
        if ($config['schema_version'] >= 2) {
            app(OperationalProfile::class)->validate($config['profiles'], $config, $publishing);
        } else {
            foreach (self::PROFILES as $name => $value) {
                if (! array_key_exists($name, $config['profiles']) || $config['profiles'][$name] !== $value) {
                    $this->fail('profiles', 'Profil proses Fase 3–4 dilindungi; pengajuan belum boleh dibuka.');
                }
            }
        }
        if ($config['schema_version'] === 1 && array_diff(array_keys($config['profiles']), array_keys(self::PROFILES))) {
            $this->fail('profiles', 'Profil tidak dikenal.');
        }
        $maps = [];
        foreach (['services', 'categories', 'catalog', 'forms', 'locations'] as $section) {
            if (! array_is_list($config[$section])) {
                $this->fail($section, 'Daftar harus berupa array berurutan.');
            }
            $maps[$section] = [];
            foreach ($config[$section] as $i => $entry) {
                if (isset($maps[$section][$entry['key']])) {
                    $this->fail($section.'.'.$i.'.key', 'Kode harus unik.');
                }
                $maps[$section][$entry['key']] = $entry;
            }
        }
        foreach (['categories', 'locations'] as $section) {
            $edges = [];
            foreach ($maps[$section] as $name => $entry) {
                $parent = $entry['parent_key'] ?? null;
                if ($parent && ! isset($maps[$section][$parent])) {
                    $this->fail($section, 'Parent tidak ditemukan: '.$parent);
                }
                $edges[$name] = $parent ? [$parent] : [];
            }
            $this->noCycles($edges, $section);
        }
        foreach ($config['catalog'] as $i => $item) {
            foreach (['service_key' => 'services', 'category_key' => 'categories', 'form_key' => 'forms'] as $attribute => $section) {
                $reference = $maps[$section][$item[$attribute]] ?? null;
                if (! $reference) {
                    $this->fail('catalog.'.$i.'.'.$attribute, 'Referensi tidak ditemukan.');
                }
                if ($item['is_active'] && isset($reference['is_active']) && ! $reference['is_active']) {
                    $this->fail('catalog.'.$i, 'Item aktif harus memakai master aktif.');
                }
            }
            if ($maps['forms'][$item['form_key']]['record_type'] !== $item['record_type']) {
                $this->fail('catalog.'.$i, 'Jenis form tidak sesuai jenis katalog.');
            }
        }
        foreach ($config['forms'] as $i => $form) {
            if (! array_is_list($form['fields'])) {
                $this->fail('forms.'.$i, 'Daftar field tidak valid.');
            }
            $fields = [];
            foreach ($form['fields'] as $j => $field) {
                $path = 'forms.'.$i.'.fields.'.$j;
                if (in_array($field['key'], self::RESERVED, true)) {
                    $this->fail($path.'.key', 'Kode ini dikendalikan sistem.');
                }
                if (isset($fields[$field['key']])) {
                    $this->fail($path.'.key', 'Kode field harus unik dalam form.');
                }
                $fields[$field['key']] = $field;
                foreach (['visible_roles', 'read_only_roles'] as $roleList) {
                    if (count(array_unique($field[$roleList] ?? [])) !== count($field[$roleList] ?? [])) {
                        $this->fail($path, 'Peran tidak boleh duplikat.');
                    }
                }
                if (array_diff($field['read_only_roles'] ?? [], $field['visible_roles'])) {
                    $this->fail($path, 'Peran read-only harus juga dapat melihat field.');
                }
                if (! empty($field['read_only_roles']) && (($field['required'] ?? false) || ! empty($field['required_when'])) && (! array_key_exists('default', $field) || $field['default'] === null || $field['default'] === '')) {
                    $this->fail($path, 'Field wajib yang read-only harus memiliki default server yang valid.');
                }
                if (! array_is_list($field['visible_roles']) || ! array_is_list($field['read_only_roles'] ?? []) || ! array_is_list($field['options'] ?? [])) {
                    $this->fail($path, 'Daftar peran dan pilihan harus berurutan.');
                }
                if (in_array($field['type'], ['select', 'multiselect', 'radio'], true) && empty($field['options'])) {
                    $this->fail($path, 'Pilihan wajib tersedia.');
                }
                if (count(array_unique(array_column($field['options'] ?? [], 'value'))) !== count($field['options'] ?? [])) {
                    $this->fail($path, 'Kode pilihan tidak boleh duplikat.');
                }
                if ($field['type'] === 'reference' && empty($field['source'])) {
                    $this->fail($path, 'Sumber master wajib dipilih.');
                }
                if (($field['source'] ?? null) === 'support_teams' && in_array('requester', $field['visible_roles'], true)) {
                    $this->fail($path, 'Referensi tim internal hanya untuk petugas.');
                }
                foreach ([['min', 'max'], ['min_length', 'max_length']] as [$min, $max]) {
                    if (isset($field[$min], $field[$max]) && $field[$min] > $field[$max]) {
                        $this->fail($path, 'Batas minimum melebihi maksimum.');
                    }
                }
                if (array_key_exists('default', $field) && $field['default'] !== null) {
                    (new FormEngine)->validateDefault($field, $config, $path);
                }
            }
            $edges = [];
            foreach ($fields as $name => $field) {
                $dependencies = [];
                foreach (['visible_when', 'required_when'] as $condition) {
                    if (! empty($field[$condition])) {
                        $dependencies = array_merge($dependencies, $this->condition($field[$condition], $fields, 'forms.'.$i.'.'.$name.'.'.$condition, $config));
                    }
                }
                foreach ($dependencies as $dependency) {
                    if (array_diff($field['visible_roles'], $fields[$dependency]['visible_roles'])) {
                        $this->fail('forms.'.$i, 'Kondisi tidak boleh bergantung pada field yang tersembunyi bagi peran tersebut.');
                    }
                }
                $edges[$name] = $dependencies;
            }
            $this->noCycles($edges, 'forms.'.$i);
        }

        return $config;
    }

    private function condition(array $node, array $fields, string $path, array $configuration, int $depth = 0, int &$count = 0): array
    {
        if (++$count > 30 || $depth > 3) {
            $this->fail($path, 'Kondisi terlalu kompleks.');
        }
        if (isset($node['mode'])) {
            if (array_diff(array_keys($node), ['mode', 'rules']) || ! in_array($node['mode'], ['all', 'any'], true) || ! is_array($node['rules'] ?? null) || ! count($node['rules'])) {
                $this->fail($path, 'Grup kondisi tidak valid.');
            }
            $dependencies = [];
            foreach ($node['rules'] as $child) {
                if (! is_array($child)) {
                    $this->fail($path, 'Kondisi tidak valid.');
                }
                $dependencies = array_merge($dependencies, $this->condition($child, $fields, $path, $configuration, $depth + 1, $count));
            }

            return $dependencies;
        }
        $key = $node['field'] ?? '';
        if (array_diff(array_keys($node), ['field', 'operator', 'value']) || ! isset($fields[$key]) || ! in_array($node['operator'] ?? null, ['equals', 'not_equals', 'in', 'is_empty'], true)) {
            $this->fail($path, 'Referensi atau operator kondisi tidak valid.');
        }
        if ($fields[$key]['type'] === 'file') {
            $this->fail($path, 'File tidak dapat menjadi sumber kondisi.');
        }
        if ($node['operator'] !== 'is_empty') {
            $values = $node['operator'] === 'in' ? ($node['value'] ?? null) : [$node['value'] ?? null];
            if (! is_array($values) || ! count($values) || count($values) > 100) {
                $this->fail($path, 'Nilai kondisi tidak valid.');
            }
            foreach ($values as $value) {
                if (! is_scalar($value)) {
                    $this->fail($path, 'Nilai kondisi harus sederhana.');
                }
                if ($fields[$key]['type'] === 'checkbox' && ! is_bool($value)) {
                    $this->fail($path, 'Nilai kondisi checkbox harus true atau false.');
                }
                $probe = $fields[$key];
                $probe['default'] = $probe['type'] === 'multiselect' ? [$value] : $value;
                (new FormEngine)->validateDefault($probe, $configuration, $path);
                if (in_array($fields[$key]['type'], ['select', 'radio', 'multiselect'], true) && ! in_array($value, array_column($fields[$key]['options'], 'value'), true)) {
                    $this->fail($path, 'Kondisi merujuk pilihan yang tidak tersedia.');
                }
            }
        }

        return [$key];
    }

    private function noCycles(array $edges, string $path): void
    {
        $done = [];
        $active = [];
        $visit = function ($key) use (&$visit, &$done, &$active, $edges, $path) {
            if (isset($active[$key])) {
                $this->fail($path, 'Dependensi membentuk siklus.');
            }
            if (isset($done[$key])) {
                return;
            }
            $active[$key] = true;
            foreach ($edges[$key] ?? [] as $dependency) {
                $visit($dependency);
            }
            unset($active[$key]);
            $done[$key] = true;
        };
        foreach (array_keys($edges) as $key) {
            $visit($key);
        }
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages(['configuration.'.$key => $message]);
    }
}
