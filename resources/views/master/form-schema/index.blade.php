@extends('partials.layouts.master')

@section('title', 'Master Form Schema')
@section('title-sub', 'Konfigurasi Form')
@section('pagetitle', 'Form Schema List')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            {{-- 1. HEADER CARD --}}
            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div>
                            <h4 class="fw-bold mb-1">Form Schema Configuration</h4>
                            <p class="text-muted mb-0">Atur field formulir dinamis untuk setiap tipe layanan tiket.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('form-schema.create') }}" class="btn btn-primary shadow-sm px-4">
                                <i class="bi bi-plus-lg me-1"></i> Buat Schema Baru
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. DATA TABLE --}}
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted text-uppercase fs-11 fw-bold">
                                <tr>
                                    <th class="ps-4 py-3">Tipe Layanan</th>
                                    <th class="py-3">Jumlah Field</th>
                                    <th class="py-3">Preview Field (Key)</th>
                                    <th class="py-3">Last Updated</th>
                                    <th class="text-end pe-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                @forelse($schemas as $schema)
                                    <tr>
                                        {{-- Tipe Layanan --}}
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar-sm bg-soft-primary text-primary rounded d-flex align-items-center justify-content-center fw-bold" style="min-width: 40px; height: 40px;">
                                                    <i class="bi bi-ui-checks-grid fs-5"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-0 text-dark">{{ $schema->ticketCategory->name ?? 'Deleted Category' }}</h6>
                                                    <small class="text-muted">ID: {{ $schema->id }}</small>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Jumlah Field --}}
                                        <td>
                                            <span class="badge bg-soft-info text-info rounded-pill px-3">
                                                {{ count($schema->schema) }} Input Fields
                                            </span>
                                        </td>

                                        {{-- Preview Keys --}}
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap" style="max-width: 300px;">
                                                @foreach(array_slice($schema->schema, 0, 3) as $field)
                                                    {{-- PERBAIKAN: Gunakan ['name'] --}}
                                                    <span class="badge bg-light text-secondary border fw-normal">
                                                        {{ $field['name'] ?? $field['key'] ?? 'unknown' }}
                                                    </span>
                                                @endforeach
                                                @if(count($schema->schema) > 3)
                                                    <span class="badge bg-light text-muted border fw-normal">+{{ count($schema->schema) - 3 }} more</span>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- Updated At --}}
                                        <td>
                                            <div class="d-flex flex-column text-muted fs-12">
                                                <span class="text-dark fw-medium">{{ $schema->updated_at->format('d M Y') }}</span>
                                                <span>{{ $schema->updated_at->format('H:i') }} WIB</span>
                                            </div>
                                        </td>

                                        {{-- Action --}}
                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="{{ route('form-schema.edit', $schema->id) }}" class="btn btn-sm btn-light border text-primary">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </a>
                                                <button type="button" class="btn btn-sm btn-light border text-danger" onclick="confirmDelete('{{ $schema->id }}', '{{ $schema->ticketCategory->name }}')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                
                                                {{-- Form Delete Hidden --}}
                                                <form id="delete-form-{{ $schema->id }}" action="{{ route('form-schema.destroy', $schema->id) }}" method="POST" class="d-none">
                                                    @csrf @method('DELETE')
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="mb-3">
                                                <i class="bi bi-clipboard-x fs-1 text-muted opacity-25"></i>
                                            </div>
                                            <h6 class="text-muted fw-normal">Belum ada konfigurasi form schema.</h6>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

@section('script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Hapus Schema?',
            text: "Konfigurasi form untuk " + name + " akan dihapus. Form tiket akan kembali ke default.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-form-' + id).submit();
            }
        });
    }
</script>
@endsection
@endsection