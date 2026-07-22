@extends('partials.layouts.master')

@section('title', 'Manual Guide GA & Inventori')
@section('css')
    @include('bum.partials.mobile-style')
    <style>
        .guide-page {
            --guide-ink: #1f2933;
            --guide-muted: #7b8794;
            --guide-line: #e7ecf2;
            --guide-soft: #f8fafc;
            --guide-primary: #e21a1a;
            --guide-blue: #2563eb;
            --guide-green: #16a34a;
            --guide-amber: #d97706;
        }

        .guide-page a {
            text-decoration: none;
        }

        .guide-title {
            color: var(--guide-ink);
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: .3rem;
        }

        .guide-subtitle,
        .muted-text {
            color: var(--guide-muted);
        }

        .guide-actions,
        .quick-nav {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .guide-actions {
            justify-content: flex-end;
        }

        .guide-btn,
        .quick-chip {
            border-radius: 8px;
            font-size: .84rem;
            font-weight: 800;
            min-height: 36px;
            padding: .45rem .85rem;
        }

        .guide-panel,
        .role-card,
        .flow-card,
        .module-card,
        .rule-card {
            background: #fff;
            border: 1px solid var(--guide-line);
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(31, 41, 51, .04);
        }

        .guide-panel {
            overflow: hidden;
        }

        .guide-overview {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1.25fr) minmax(300px, .75fr);
        }

        .guide-intro {
            padding: 1.2rem;
        }

        .guide-intro-title {
            color: var(--guide-ink);
            font-size: 1.05rem;
            font-weight: 800;
        }

        .quick-chip {
            background: var(--guide-soft);
            border: 1px solid var(--guide-line);
            color: #364152;
            display: inline-flex;
            align-items: center;
            gap: .45rem;
        }

        .quick-chip:hover {
            border-color: var(--guide-primary);
            color: var(--guide-primary);
        }

        .guide-section-head {
            align-items: center;
            border-bottom: 1px solid var(--guide-line);
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            padding: 1rem 1.1rem;
        }

        .guide-section-title {
            color: var(--guide-ink);
            font-size: .95rem;
            font-weight: 800;
        }

        .guide-section-body {
            padding: 1rem 1.1rem;
        }

        .role-grid {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 1.2rem;
        }

        .role-card {
            align-items: flex-start;
            display: flex;
            gap: .75rem;
            padding: .9rem;
        }

        .icon-box,
        .flow-number {
            align-items: center;
            border-radius: 8px;
            display: flex;
            flex: 0 0 42px;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .role-title,
        .flow-title,
        .module-title,
        .rule-title {
            color: var(--guide-ink);
            font-weight: 800;
        }

        .flow-track {
            display: grid;
            gap: .85rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .flow-card {
            padding: 1rem;
        }

        .flow-number {
            background: rgba(255, 107, 44, .1);
            color: var(--guide-primary);
            font-weight: 900;
            margin-bottom: .85rem;
        }

        .module-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .module-card {
            display: flex;
            flex-direction: column;
            gap: .85rem;
            min-height: 228px;
            padding: 1rem;
        }

        .module-card-top {
            align-items: flex-start;
            display: flex;
            gap: .8rem;
            justify-content: space-between;
        }

        .module-meta {
            display: grid;
            gap: .65rem;
        }

        .module-meta-row {
            display: grid;
            gap: .2rem;
        }

        .meta-label {
            color: var(--guide-muted);
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .module-action {
            margin-top: auto;
        }

        .rule-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .rule-card {
            padding: 1rem;
        }

        .status-stack {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .soft-badge {
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 800;
            padding: .32rem .55rem;
        }

        @media (max-width: 1199.98px) {
            .guide-overview,
            .flow-track,
            .module-grid,
            .rule-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .guide-actions,
            .guide-section-head {
                align-items: stretch;
                flex-direction: column;
            }

            .role-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@section('content')
<div class="container-fluid pb-5 bum-page guide-page">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4">
        <div>
            <h3 class="guide-title">Manual Guide GA & Inventori</h3>
            <p class="guide-subtitle mb-0">Panduan kerja ringkas untuk request GA, gudang ATK/RTK, stok, penerimaan, opname, analytics, dan laporan.</p>
        </div>
        <div class="guide-actions">
            <a href="{{ route('bum.dashboard') }}" class="btn btn-light border guide-btn">Ringkasan</a>
            <a href="{{ route('ticket.ga-permintaan-temuan.create') }}" class="btn btn-outline-primary guide-btn">Input Permintaan / Temuan</a>
            <a href="{{ route('ticket.atk-rtk.warehouse') }}" class="btn btn-primary guide-btn">Gudang ATK/RTK</a>
        </div>
    </div>

    <div class="guide-overview mb-4">
        <div class="guide-panel">
            <div class="guide-intro">
                <div class="guide-intro-title mb-2">Gunakan halaman ini sebagai peta kerja modul GA.</div>
                <div class="muted-text mb-3">Mulai dari input kebutuhan, review, penerimaan barang, mutasi stok, opname, sampai laporan. Setiap kartu modul di bawah punya tombol langsung ke halaman terkait.</div>
                <div class="quick-nav">
                    <a href="#alur" class="quick-chip"><i class="bi bi-diagram-3"></i> Alur Kerja</a>
                    <a href="#modul" class="quick-chip"><i class="bi bi-grid"></i> Panduan Modul</a>
                    <a href="#stok" class="quick-chip"><i class="bi bi-box-seam"></i> Aturan Stok</a>
                    <a href="#status" class="quick-chip"><i class="bi bi-flag"></i> Status Workflow</a>
                </div>
            </div>
        </div>

        <div class="guide-panel">
            <div class="role-grid">
                @foreach([
                    ['title' => 'Pemohon', 'desc' => 'Input permintaan/temuan atau request ATK/RTK.', 'icon' => 'bi-person-check', 'color' => 'primary'],
                    ['title' => 'Approver', 'desc' => 'Approve atau reject request yang membutuhkan persetujuan.', 'icon' => 'bi-shield-check', 'color' => 'success'],
                    ['title' => 'Admin GA', 'desc' => 'Review, penerimaan, handover, opname, dan stok.', 'icon' => 'bi-box-seam', 'color' => 'warning'],
                    ['title' => 'Manajemen', 'desc' => 'Pantau analytics, forecast, dan laporan periodik.', 'icon' => 'bi-graph-up-arrow', 'color' => 'info'],
                ] as $role)
                    <div class="role-card">
                        <div class="icon-box bg-{{ $role['color'] }} bg-opacity-10 text-{{ $role['color'] }}">
                            <i class="bi {{ $role['icon'] }}"></i>
                        </div>
                        <div>
                            <div class="role-title">{{ $role['title'] }}</div>
                            <div class="muted-text small mt-1">{{ $role['desc'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="guide-panel mb-4" id="alur">
        <div class="guide-section-head">
            <div>
                <div class="guide-section-title">Alur Kerja Utama</div>
                <div class="muted-text small">Urutan proses dari kebutuhan user sampai kontrol stok.</div>
            </div>
        </div>
        <div class="guide-section-body">
            <div class="flow-track">
                @foreach([
                    ['title' => 'Input Kebutuhan', 'desc' => 'User membuat laporan Permintaan/Temuan GA atau request ATK/RTK.'],
                    ['title' => 'Review & Approval', 'desc' => 'Approver/GA mengecek kebutuhan, stok, dan kelayakan request.'],
                    ['title' => 'Mutasi Barang', 'desc' => 'Penerimaan masuk Gudang Besar, transfer mengisi Gudang Kecil, handover mengurangi Gudang Kecil.'],
                    ['title' => 'Monitoring', 'desc' => 'Dashboard, forecast, opname, dan laporan dipakai untuk kontrol periodik.'],
                ] as $index => $step)
                    <div class="flow-card">
                        <div class="flow-number">{{ $index + 1 }}</div>
                        <div class="flow-title mb-2">{{ $step['title'] }}</div>
                        <div class="muted-text small">{{ $step['desc'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="guide-panel mb-4" id="modul">
        <div class="guide-section-head">
            <div>
                <div class="guide-section-title">Panduan Modul</div>
                <div class="muted-text small">Pilih kartu sesuai pekerjaan yang ingin dilakukan.</div>
            </div>
        </div>
        <div class="guide-section-body">
            <div class="module-grid">
                @foreach([
                    ['icon' => 'bi-speedometer2', 'color' => 'primary', 'menu' => 'Ringkasan', 'use' => 'Monitoring KPI GA, request pending, stok minimum, penerimaan, dan opname.', 'steps' => 'Gunakan slicer Permintaan/Temuan untuk fokus laporan QR.', 'output' => 'Kondisi GA terbaru.', 'route' => route('bum.dashboard')],
                    ['icon' => 'bi-qr-code-scan', 'color' => 'info', 'menu' => 'Input Permintaan / Temuan', 'use' => 'Mencatat laporan QR Code GA seperti permintaan support atau temuan fasilitas.', 'steps' => 'Isi jenis laporan, lokasi, deskripsi, prioritas, dan lampiran.', 'output' => 'Ticket GA Permintaan/Temuan.', 'route' => route('ticket.ga-permintaan-temuan.create')],
                    ['icon' => 'bi-clipboard-plus', 'color' => 'warning', 'menu' => 'Request ATK / RTK', 'use' => 'User meminta barang ATK/RTK untuk kebutuhan operasional.', 'steps' => 'Isi barang, jumlah, lokasi, tanggal dibutuhkan, dan approver.', 'output' => 'Ticket ATK/RTK.', 'route' => route('ticket.atk-rtk.create')],
                    ['icon' => 'bi-box-arrow-up', 'color' => 'success', 'menu' => 'Gudang ATK/RTK', 'use' => 'Admin GA memproses antrian request, review stok Gudang Kecil/Besar, transfer, dan handover barang.', 'steps' => 'Buka ticket, cek stok, transfer dari Gudang Besar jika perlu, lalu handover jika barang siap.', 'output' => 'Gudang Kecil berkurang dan stock card tercatat.', 'route' => route('ticket.atk-rtk.warehouse')],
                    ['icon' => 'bi-boxes', 'color' => 'primary', 'menu' => 'Master Barang', 'use' => 'Mengelola barang, UOM besar/kecil, konversi, stok minimum, buffer, lokasi, dan status aktif.', 'steps' => 'Tambah/edit barang, cek detail, atau adjustment manual untuk Gudang Besar/Kecil.', 'output' => 'Master item dan saldo dua gudang terkini.', 'route' => route('bum.items')],
                    ['icon' => 'bi-truck', 'color' => 'success', 'menu' => 'Penerimaan Barang', 'use' => 'Mencatat barang masuk dari PO, DO/SJ, dan GR.', 'steps' => 'Buat dokumen, input item, lalu update qty terima/tolak.', 'output' => 'Gudang Besar bertambah sesuai qty diterima.', 'route' => route('bum.receivings')],
                    ['icon' => 'bi-card-checklist', 'color' => 'info', 'menu' => 'Stock Card', 'use' => 'Audit histori mutasi stok masuk, keluar, adjustment, dan referensi.', 'steps' => 'Filter barang/tanggal, sort kolom, buka referensi dokumen.', 'output' => 'Audit trail stok per item.', 'route' => route('bum.stock-card')],
                    ['icon' => 'bi-clipboard-data', 'color' => 'warning', 'menu' => 'Stock Opname', 'use' => 'Mencocokkan stok sistem dengan stok fisik.', 'steps' => 'Pilih periode, pilih barang, isi stok fisik, simpan opname.', 'output' => 'Adjustment otomatis jika ada selisih.', 'route' => route('bum.opnames')],
                    ['icon' => 'bi-graph-up-arrow', 'color' => 'primary', 'menu' => 'Analytics & Forecast', 'use' => 'Melihat tren pemakaian, forecast stock-out, dan rekomendasi pengadaan.', 'steps' => 'Gunakan filter dan cek tabel forecast/rekomendasi.', 'output' => 'Insight restock dan risiko stok habis.', 'route' => route('bum.analytics')],
                    ['icon' => 'bi-file-earmark-text', 'color' => 'secondary', 'menu' => 'Laporan', 'use' => 'Melihat rekap pemakaian, penerimaan, dan konsumsi rapat per periode.', 'steps' => 'Pilih periode, sort/pagination tabel sesuai kebutuhan.', 'output' => 'Rekap bulanan GA & Inventori.', 'route' => route('bum.reports')],
                ] as $module)
                    <div class="module-card">
                        <div class="module-card-top">
                            <div class="d-flex gap-3">
                                <div class="icon-box bg-{{ $module['color'] }} bg-opacity-10 text-{{ $module['color'] }}">
                                    <i class="bi {{ $module['icon'] }}"></i>
                                </div>
                                <div>
                                    <div class="module-title">{{ $module['menu'] }}</div>
                                    <div class="muted-text small mt-1">{{ $module['use'] }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="module-meta">
                            <div class="module-meta-row">
                                <div class="meta-label">Langkah</div>
                                <div class="small">{{ $module['steps'] }}</div>
                            </div>
                            <div class="module-meta-row">
                                <div class="meta-label">Output</div>
                                <div class="small">{{ $module['output'] }}</div>
                            </div>
                        </div>
                        <div class="module-action">
                            <a href="{{ $module['route'] }}" class="btn btn-sm btn-light border guide-btn">Buka Modul</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6" id="stok">
            <div class="guide-panel h-100">
                <div class="guide-section-head">
                    <div>
                        <div class="guide-section-title">Aturan Mutasi Stok</div>
                        <div class="muted-text small">Prinsip utama agar saldo stok tetap valid.</div>
                    </div>
                </div>
                <div class="guide-section-body">
                    <div class="rule-grid">
                        @foreach([
                            ['title' => 'Penerimaan', 'desc' => 'Qty diterima menambah Gudang Besar. Qty ditolak tidak masuk stok.'],
                            ['title' => 'Transfer Gudang', 'desc' => 'Pengambilan dari Gudang Besar otomatis menambah Gudang Kecil sesuai konversi UOM.'],
                            ['title' => 'Handover ATK/RTK', 'desc' => 'Qty fulfilled mengurangi Gudang Kecil dan tidak boleh melebihi qty approved.'],
                            ['title' => 'Manual Adjustment & Opname', 'desc' => 'Adjustment dan opname bisa dipilih untuk Gudang Besar atau Gudang Kecil dan tercatat di stock card.'],
                        ] as $rule)
                            <div class="rule-card">
                                <div class="rule-title">{{ $rule['title'] }}</div>
                                <div class="muted-text small mt-1">{{ $rule['desc'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6" id="status">
            <div class="guide-panel h-100">
                <div class="guide-section-head">
                    <div>
                        <div class="guide-section-title">Status Workflow ATK/RTK</div>
                        <div class="muted-text small">Status yang umum muncul pada proses gudang.</div>
                    </div>
                </div>
                <div class="guide-section-body">
                    <div class="status-stack mb-3">
                        @foreach(['WAITING_MANAGER_APPROVAL', 'WAITING_BUM_REVIEW', 'STOCK_CHECKED', 'WAITING_PROCUREMENT', 'READY_TO_HANDOVER', 'HANDED_OVER', 'CANCELLED'] as $status)
                            <span class="badge bg-light text-dark border soft-badge">{{ $status }}</span>
                        @endforeach
                    </div>
                    <div class="muted-text small">
                        Untuk memproses ATK/RTK, buka menu <strong>Gudang ATK/RTK</strong>, pilih ticket, lalu gunakan panel <strong>Proses ATK/RTK</strong> pada detail ticket.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
