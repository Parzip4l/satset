@extends(($isPublic ?? false) ? 'partials.layouts.master_auth' : 'partials.layouts.master')

@section('title', 'Permintaan ATK / RTK')
@section('pagetitle', 'ATK / RTK')
@section('title-sub', 'Request Form')

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    .atk-page {
        --atk-ink: #2d2416;
        --atk-muted: #6f6a5c;
        --atk-line: #e7dcc7;
        --atk-brand: #e21a1a;
    }

    .atk-shell {
        background: linear-gradient(135deg, #fff8ee 0%, #ffffff 52%, #f7fbff 100%);
        border: 1px solid rgba(45, 36, 22, 0.08);
        border-radius: 28px;
        box-shadow: 0 22px 60px rgba(45, 36, 22, 0.08);
    }

    .atk-card {
        background: #fff;
        border: 1px solid var(--atk-line);
        border-radius: 24px;
        box-shadow: 0 16px 40px rgba(45, 36, 22, 0.05);
    }

    .atk-page .form-label {
        font-weight: 700;
        color: var(--atk-ink);
    }

    .atk-page .form-control,
    .atk-page .form-select {
        min-height: 50px;
        border-radius: 16px;
        border: 1px solid var(--atk-line);
        background: #fffdf9;
        padding-inline: 1rem;
    }

    .atk-page textarea.form-control {
        min-height: 120px;
    }

    .atk-page .select2-container--bootstrap-5 {
        width: 100% !important;
    }

    .atk-page .select2-container--bootstrap-5 .select2-selection {
        align-items: center;
        background: #fffdf9;
        border-color: var(--atk-line);
        border-radius: 16px;
        display: flex;
        min-height: 50px;
        padding: 0 2.75rem 0 1rem;
    }

    .atk-page .select2-container--bootstrap-5.select2-container--focus .select2-selection,
    .atk-page .select2-container--bootstrap-5.select2-container--open .select2-selection {
        border-color: var(--atk-brand);
        box-shadow: 0 0 0 .2rem rgba(201, 131, 31, .12);
    }

    .atk-page .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        color: #212529;
        font-size: 1rem;
        line-height: 1.35;
        padding: 0;
    }

    .atk-page .select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder {
        color: #9aa4b2;
    }

    .atk-page .select2-container--bootstrap-5 .select2-selection--single .select2-selection__clear {
        color: #6b7280;
        font-size: 1.15rem;
        height: 28px;
        line-height: 28px;
        margin: 0;
        position: absolute;
        right: 2.25rem;
        top: 50%;
        transform: translateY(-50%);
        width: 28px;
    }

    .atk-page .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        height: 50px;
        right: .9rem;
        top: 0;
        width: 20px;
    }

    .atk-page .select2-dropdown {
        border-color: var(--atk-line);
        border-radius: 14px;
        overflow: hidden;
    }

    .atk-page .select2-search--dropdown {
        padding: .75rem;
    }

    .atk-page .select2-search--dropdown .select2-search__field {
        border: 1px solid var(--atk-line);
        border-radius: 12px;
        min-height: 42px;
        padding: .5rem .8rem;
    }

    .atk-item-row {
        background: #fffdf9;
        border: 1px solid var(--atk-line);
        border-radius: 18px;
        padding: 16px;
    }

    .atk-item-total {
        background: rgba(226, 26, 26, .08);
        border-radius: 16px;
        color: #991b1b;
        font-weight: 800;
        padding: 14px 16px;
    }
</style>
@endsection

@section('content')
@php
    $oldItems = old('payload.items', [['item_id' => '', 'quantity' => 1]]);
    if (!is_array($oldItems) || count($oldItems) === 0) {
        $oldItems = [['item_id' => '', 'quantity' => 1]];
    }
@endphp
<div class="container-fluid atk-page">
    <div class="atk-shell p-4 p-xl-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(226,26,26,.12); color:#b91c1c;">ATK / RTK Request</span>
                <h3 class="fw-bold text-dark mb-2">Form kebutuhan ATK dan RTK.</h3>
                <p class="text-muted mb-0">Ajukan kebutuhan operasional kantor melalui jalur ATK/RTK.</p>
            </div>
            @unless($isPublic ?? false)
                <div class="d-flex gap-2">
                    <a href="{{ route('ticket.index') }}" class="btn btn-light border rounded-pill px-4">Menu Requests</a>
                    <a href="{{ route('ticket.general') }}" class="btn btn-outline-secondary rounded-pill px-4">Lihat General</a>
                </div>
            @endunless
        </div>

        <form action="{{ ($isPublic ?? false) ? route('public.ticket.atk-rtk.store') : route('ticket.store') }}" method="POST" id="atkRtkForm">
            @csrf
            <input type="hidden" name="request_type" value="atk_rtk">
            <input type="hidden" name="ticket_category_id" value="{{ $serviceRequestId ?? '' }}">
            <input type="hidden" name="priority_id" value="{{ $mediumPriorityId ?? '' }}">
            <input type="hidden" name="impact_id" value="{{ $mediumImpactId ?? '' }}">
            <input type="hidden" name="urgency_id" value="{{ $mediumUrgencyId ?? '' }}">
            <input type="hidden" name="category_id" value="{{ old('category_id', $atkRtkCategoryId ?? ($categories[0]['id'] ?? '')) }}">

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="atk-card p-4 p-xl-5">
                        @if($isPublic ?? false)
                            <div class="mb-4">
                                <h5 class="fw-bold text-dark mb-3">Data Pemohon</h5>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Nama Pemohon</label>
                                        <input type="text" name="reporter_name" class="form-control" value="{{ old('reporter_name') }}" placeholder="Nama lengkap" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email Pemohon</label>
                                        <input type="email" name="reporter_email" class="form-control" value="{{ old('reporter_email') }}" placeholder="nama@email.com" required>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-4">
                        @endif

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Judul Permintaan</label>
                                <input type="text" name="payload[request_subject]" class="form-control" value="{{ old('payload.request_subject') }}" placeholder="Contoh: Pengadaan ATK untuk onboarding Mei" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jenis Barang</label>
                                <select name="payload[item_type]" class="form-select" required>
                                    <option value="">Pilih jenis barang</option>
                                    @foreach (['ATK', 'RTK', 'ATK + RTK', 'Lainnya'] as $type)
                                        <option value="{{ $type }}" {{ old('payload.item_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                                    <div>
                                        <label class="form-label mb-1">Barang yang Diminta</label>
                                        <div class="text-muted small">Harga otomatis mengikuti master barang. Tambahkan baris jika request lebih dari satu barang.</div>
                                    </div>
                                    <button type="button" class="btn btn-outline-danger rounded-pill px-3" id="addAtkItem">
                                        <i class="bi bi-plus-lg me-1"></i> Tambah Barang
                                    </button>
                                </div>

                                <div id="atkItems" class="d-grid gap-3">
                                    @foreach($oldItems as $index => $row)
                                        <div class="atk-item-row" data-atk-item-row>
                                            <div class="row g-3 align-items-end">
                                                <div class="col-md-7">
                                                    <label class="form-label">Item Master</label>
                                                    <select name="payload[items][{{ $index }}][item_id]" class="form-select atk-item-select" data-placeholder="Cari kode atau nama barang" required>
                                                        <option value="">Pilih barang</option>
                                                        @foreach(($consumableItems ?? collect()) as $item)
                                                        <option value="{{ $item->id }}"
                                                                data-price="{{ (float) $item->unit_price }}"
                                                                data-large-price="{{ (float) $item->unit_price * max(1, (int) $item->conversion_qty) }}"
                                                                data-unit="{{ $item->small_uom }}"
                                                                data-large-unit="{{ $item->large_uom }}"
                                                                data-conversion="{{ max(1, (int) $item->conversion_qty) }}"
                                                                @selected((string) data_get($row, 'item_id') === (string) $item->id)>
                                                                {{ $item->code }} - {{ $item->name }} | Rp{{ number_format((float) $item->unit_price, 0, ',', '.') }}/{{ $item->small_uom }} (1 {{ $item->large_uom }} = {{ $item->conversion_qty }} {{ $item->small_uom }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Qty</label>
                                                    <input type="number" min="1" name="payload[items][{{ $index }}][quantity]" class="form-control atk-item-qty" value="{{ data_get($row, 'quantity', 1) }}" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-light border rounded-pill w-100" data-remove-atk-item>
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="col-12">
                                                    <div class="text-muted small" data-atk-line-total>Subtotal: Rp0</div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="atk-item-total mt-3 d-flex justify-content-between align-items-center">
                                    <span>Total Estimasi</span>
                                    <span id="atkGrandTotal">Rp0</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Dibutuhkan</label>
                                <input type="date" name="payload[needed_date]" class="form-control" value="{{ old('payload.needed_date') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lokasi Pengiriman</label>
                                <input type="text" name="payload[delivery_location]" class="form-control" value="{{ old('payload.delivery_location') }}" placeholder="Lokasi / ruangan penerima" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PIC Penerima</label>
                                <input type="text" name="payload[recipient_pic]" class="form-control" value="{{ old('payload.recipient_pic') }}" placeholder="Nama PIC penerima barang">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Atasan yang Menyetujui</label>
                                <select name="payload[supervisor_id]" id="atkSupervisorId" class="form-select atk-select" data-placeholder="Cari nama approver">
                                    <option value="">Tidak perlu approval atasan</option>
                                    @foreach(($approvers ?? collect()) as $approver)
                                        <option value="{{ $approver->id }}" @selected(old('payload.supervisor_id') == $approver->id)>
                                            {{ $approver->name }}{{ $approver->role ? ' - ' . ucfirst($approver->role) : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="text-muted small mt-2">Approver akan ditentukan otomatis dari Intranet jika request membutuhkan approval.</div>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">
                            <p class="text-muted small mb-0">Threshold approval atasan: <strong>Rp{{ number_format($approvalThreshold ?? 100000, 0, ',', '.') }}</strong>.</p>
                            <div class="d-flex gap-2">
                                @unless($isPublic ?? false)
                                    <a href="{{ route('ticket.index') }}" class="btn btn-light border rounded-pill px-4">Batal</a>
                                @endunless
                                <button type="submit" class="btn rounded-pill px-4 text-white" style="background:#e21a1a;">
                                    <i class="bi bi-send me-1"></i> Kirim Permintaan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="atk-card p-4">
                        <h5 class="fw-bold text-dark mb-3">Ringkasan Alur</h5>
                        <div class="small text-muted mb-3">Alur permintaan ATK/RTK dari pengajuan sampai distribusi barang.</div>
                        <div class="border rounded-4 p-3 mb-3" style="background:#fffaf1;">
                            <div class="fw-semibold text-dark mb-1">1. Pengajuan</div>
                            <div class="text-muted small">Pemohon mengisi kebutuhan barang dan lokasi pengiriman.</div>
                        </div>
                        <div class="border rounded-4 p-3 mb-3" style="background:#fffaf1;">
                            <div class="fw-semibold text-dark mb-1">2. Verifikasi</div>
                            <div class="text-muted small">Unit terkait meninjau daftar barang, qty, stok, dan estimasi dari master.</div>
                        </div>
                        <div class="border rounded-4 p-3" style="background:#fffaf1;">
                            <div class="fw-semibold text-dark mb-1">3. Distribusi</div>
                            <div class="text-muted small">Barang diproses lalu diserahkan ke PIC penerima.</div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let atkItemIndex = {{ count($oldItems) }};

    function rupiah(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(value || 0);
    }

    function initAtkSelect(context = document) {
        $(context).find('.atk-select, .atk-item-select').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: function () {
                return $(this).data('placeholder') || 'Pilih data';
            }
        });
    }

    function updateAtkTotals() {
        let grandTotal = 0;

        $('[data-atk-item-row]').each(function () {
            const row = $(this);
            const option = row.find('.atk-item-select option:selected');
            const price = Number(option.data('price') || 0);
            const largePrice = Number(option.data('large-price') || 0);
            const unit = option.data('unit') || '';
            const largeUnit = option.data('large-unit') || '';
            const conversion = Number(option.data('conversion') || 1);
            const qty = Number(row.find('.atk-item-qty').val() || 0);
            const subtotal = price * qty;
            grandTotal += subtotal;

            row.find('[data-atk-line-total]').text(
                `Harga satuan kecil: ${rupiah(price)}${unit ? ' / ' + unit : ''} | Estimasi ${largeUnit}: ${rupiah(largePrice)} (${conversion} ${unit}) | Subtotal: ${rupiah(subtotal)}`
            );
        });

        $('#atkGrandTotal').text(rupiah(grandTotal));
        return grandTotal;
    }

    function reindexAtkItems() {
        $('[data-atk-item-row]').each(function (index) {
            $(this).find('.atk-item-select').attr('name', `payload[items][${index}][item_id]`);
            $(this).find('.atk-item-qty').attr('name', `payload[items][${index}][quantity]`);
        });
        atkItemIndex = $('[data-atk-item-row]').length;
    }

    $('#addAtkItem').on('click', function () {
        const firstRow = $('[data-atk-item-row]').first();
        const newRow = firstRow.clone(false);

        newRow.find('.select2-container').remove();
        newRow.find('select').removeClass('select2-hidden-accessible').removeAttr('data-select2-id aria-hidden tabindex').val('');
        newRow.find('option').removeAttr('data-select2-id');
        newRow.find('.atk-item-qty').val(1);
        newRow.find('[data-atk-line-total]').text('Subtotal: Rp0');

        $('#atkItems').append(newRow);
        reindexAtkItems();
        initAtkSelect(newRow);
        updateAtkTotals();
    });

    $(document).on('click', '[data-remove-atk-item]', function () {
        if ($('[data-atk-item-row]').length === 1) {
            $(this).closest('[data-atk-item-row]').find('.atk-item-select').val('').trigger('change');
            $(this).closest('[data-atk-item-row]').find('.atk-item-qty').val(1);
            updateAtkTotals();
            return;
        }

        $(this).closest('[data-atk-item-row]').remove();
        reindexAtkItems();
        updateAtkTotals();
    });

    $(document).on('change keyup', '.atk-item-select, .atk-item-qty', updateAtkTotals);
    $('#atkRtkForm').on('submit', function () {
        updateAtkTotals();
    });
    initAtkSelect();
    updateAtkTotals();

    @if ($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'Form belum lengkap',
            text: '{{ $errors->first() }}',
            confirmButtonColor: '#e21a1a'
        });
    @endif

    @if (session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: '{{ session('success') }}',
            confirmButtonColor: '#e21a1a'
        });
    @endif
</script>
@endsection
