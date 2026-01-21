@extends('partials.layouts.master')

@section('title', 'Edit Schema')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            
            <form action="{{ route('form-schema.update', $formSchema->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                             <a href="{{ route('form-schema.index') }}" class="btn btn-light btn-icon rounded-circle"><i class="bi bi-arrow-left"></i></a>
                             <h5 class="mb-0 fw-bold">Edit Form: {{ $formSchema->ticketCategory->name }}</h5>
                        </div>

                        {{-- 1. Pilih Kategori --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold">Tipe Layanan Tiket</label>
                            <select name="ticket_category_id" class="form-select form-select-lg" required>
                                <option value="{{ $formSchema->ticket_category_id }}" selected>{{ $formSchema->ticketCategory->name }}</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 2. Dynamic Field Builder --}}
                        <div class="bg-light p-4 rounded-3 border border-dashed">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary mb-0"><i class="bi bi-layers me-2"></i>Design Input Form</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addFieldRow()">
                                    <i class="bi bi-plus-lg"></i> Tambah Field
                                </button>
                            </div>

                            <div class="table-responsive bg-white rounded shadow-sm">
                                <table class="table table-bordered mb-0">
                                    <thead class="bg-light text-muted fs-11 text-uppercase">
                                        <tr>
                                            <th style="width: 20%">Label</th>
                                            <th style="width: 20%">Name (ID Database)</th>
                                            <th style="width: 15%">Tipe Input</th>
                                            <th style="width: 25%">Options (Khusus Select)</th>
                                            <th style="width: 10%">Wajib?</th>
                                            <th style="width: 5%" class="text-center"><i class="bi bi-trash"></i></th>
                                        </tr>
                                    </thead>
                                    <tbody id="builderBody">
                                        {{-- Rows added via JS --}}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary px-4 fw-bold">Update Schema</button>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    let rowIdx = 0;

    // Fungsi sama persis dengan Create
    function addFieldRow(data = null) {
        let label = data ? (data.label || '') : '';
        // FIX: Cek data.name dulu, baru fallback ke data.key (untuk kompatibilitas data lama)
        let name  = data ? (data.name || data.key || '') : ''; 
        let type  = data ? (data.type || 'text') : 'text';
        let required = data ? (data.required ? '1' : '0') : '1'; 
        
        // Handle Options
        let optionsVal = '';
        if (data && data.options && Array.isArray(data.options)) {
            optionsVal = data.options.join(',');
        } else if (data && data.options) {
            optionsVal = data.options;
        }
        
        let i = rowIdx; 
        let displayOptions = (type === 'select') ? 'block' : 'none';

        let html = `
            <tr id="row-${i}">
                <td>
                    <input type="text" name="schema[${i}][label]" class="form-control form-control-sm label-input" data-id="${i}" value="${label}" required>
                </td>
                <td>
                    {{-- FIX: name="...[name]" --}}
                    <input type="text" name="schema[${i}][name]" id="name-${i}" class="form-control form-control-sm bg-light text-monospace" value="${name}" required readonly>
                </td>
                <td>
                    <select name="schema[${i}][type]" class="form-select form-select-sm type-select" onchange="toggleOptions(${i})">
                        <option value="text" ${type === 'text' ? 'selected' : ''}>Text</option>
                        <option value="textarea" ${type === 'textarea' ? 'selected' : ''}>Text Area</option>
                        <option value="number" ${type === 'number' ? 'selected' : ''}>Number</option>
                        <option value="date" ${type === 'date' ? 'selected' : ''}>Date</option>
                        <option value="time" ${type === 'time' ? 'selected' : ''}>Time</option>
                        <option value="select" ${type === 'select' ? 'selected' : ''}>Select (Dropdown)</option>
                    </select>
                </td>
                <td>
                    <input type="text" name="schema[${i}][options]" id="options-${i}" class="form-control form-control-sm" placeholder="A,B,C" value="${optionsVal}" style="display: ${displayOptions};">
                </td>
                <td>
                    <select name="schema[${i}][required]" class="form-select form-select-sm">
                        <option value="1" ${required == '1' ? 'selected' : ''}>Ya</option>
                        <option value="0" ${required == '0' ? 'selected' : ''}>Tidak</option>
                    </select>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger border-0" onclick="removeRow(${i})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        $('#builderBody').append(html);
        rowIdx++;
    }

    function removeRow(index) {
        $('#row-' + index).remove();
    }

    function toggleOptions(id) {
        let type = $(`#row-${id} .type-select`).val();
        if (type === 'select') {
            $(`#options-${id}`).show().attr('required', true);
        } else {
            $(`#options-${id}`).hide().removeAttr('required').val('');
        }
    }

    $(document).on('keyup', '.label-input', function() {
        let id = $(this).data('id');
        let text = $(this).val();
        let slug = text.toLowerCase().replace(/[^\w\s-]/g, '').replace(/[\s_-]+/g, '_').replace(/^-+|-+$/g, '');
        $('#name-' + id).val(slug);
    });

    // --- LOGIKA LOAD DATA EXISTING ---
    $(document).ready(function() {
        const existingData = @json($formSchema->schema);

        if (existingData && existingData.length > 0) {
            existingData.forEach(function(item) {
                addFieldRow(item);
            });
        } else {
            addFieldRow(); 
        }
    });
</script>

<style>
    .text-monospace { font-family: 'Courier New', Courier, monospace; font-weight: 600; color: #d63384; }
    .bg-light { background-color: #f8fafc !important; }
</style>
@endsection