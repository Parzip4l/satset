@extends('partials.layouts.master')

@section('title', 'Ticket #' . $ticket->ticket_no)

@section('css')
    {{-- 1. Plugins --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <style>
        :root {
            /* Warna LRT Jakarta */
            --lrt-red: #dc2626;
            --lrt-orange: #e21a1a;
            
            --primary-color: var(--lrt-red);
            --primary-hover: #b91c1c;
            --secondary-color: var(--lrt-orange);
            
            --bg-body: #f8fafc;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --card-radius: 16px;
        }

        body { 
            background-color: var(--bg-body); 
            color: var(--text-dark);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        /* --- 1. Card System (Modern Clean) --- */
        .card-clean {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--card-radius);
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header-clean {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.025em;
            display: flex; align-items: center; gap: 8px;
        }

        /* --- 2. Chat System --- */
        .chat-container { display: flex; flex-direction: column; gap: 1.25rem; }
        .chat-item { display: flex; gap: 1rem; max-width: 85%; }
        .chat-item.me { align-self: flex-end; flex-direction: row-reverse; }
        
        .chat-avatar {
            width: 36px; height: 36px; flex-shrink: 0;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; color: #fff; font-size: 0.85rem;
        }

        .chat-bubble {
            padding: 0.875rem 1.125rem;
            border-radius: 12px;
            font-size: 0.9375rem;
            line-height: 1.6;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        .chat-item .chat-bubble { background: #f1f5f9; color: var(--text-dark); border-top-left-radius: 2px; }
        .chat-item.me .chat-bubble { background: #fee2e2; color: #7f1d1d; border-top-right-radius: 2px; }
        .chat-meta { font-size: 0.75rem; color: #94a3b8; margin-top: 6px; }
        .chat-item.me .chat-meta { text-align: right; }

        /* --- 3. Timeline Modern (Tailwind Style) --- */
        .timeline-modern { position: relative; padding-left: 1rem; }
        /* Garis vertikal tipis & halus */
        .timeline-modern::before {
            content: ''; position: absolute; top: 0; bottom: 0; left: 7px;
            width: 1px; background: #e2e8f0;
        }
        
        .timeline-item { position: relative; padding-left: 2rem; margin-bottom: 2rem; }
        .timeline-item:last-child { margin-bottom: 0; }
        
        /* Dot dengan Ring Effect */
        .timeline-marker {
            position: absolute; left: 0; top: 2px;
            width: 15px; height: 15px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid var(--lrt-orange);
            z-index: 10;
            box-shadow: 0 0 0 3px #fff; /* Ring putih di luar */
        }
        .timeline-marker.system { border-color: #cbd5e1; }

        .timeline-content {
            display: flex; flex-direction: column; gap: 0.25rem;
        }
        .timeline-user { font-weight: 600; color: var(--text-dark); font-size: 0.9rem; }
        .timeline-time { font-size: 0.75rem; color: var(--text-muted); }
        .timeline-action {
            margin-top: 0.5rem;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-dark);
        }

        /* --- 4. Form Elements & Buttons --- */
        .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection {
            border-radius: 8px;
            border-color: var(--border-color);
            min-height: 44px;
            font-size: 0.9375rem;
        }
        .form-control:focus, .form-select:focus, .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: var(--lrt-red);
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.1);
        }

        .btn-lrt-primary {
            background-color: var(--lrt-red); border-color: var(--lrt-red); color: white; font-weight: 600;
            padding: 0.625rem 1.25rem; border-radius: 8px; transition: all 0.2s;
        }
        .btn-lrt-primary:hover { background-color: #b91c1c; border-color: #b91c1c; color: white; transform: translateY(-1px); }

        .btn-lrt-outline {
            background: transparent; border: 1px solid var(--lrt-red); color: var(--lrt-red); font-weight: 600;
            padding: 0.625rem 1.25rem; border-radius: 8px; transition: all 0.2s;
        }
        .btn-lrt-outline:hover { background: #fee2e2; color: #991b1b; }

        .btn-back {
            background: #fff; border: 1px solid var(--border-color); color: var(--text-dark);
            padding: 0.5rem 1rem; border-radius: 8px; font-weight: 500; font-size: 0.875rem; transition: all 0.2s;
        }
        .btn-back:hover { border-color: #cbd5e1; background: #f8fafc; color: var(--lrt-red); }

        /* Typography */
        .page-title { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.025em; color: var(--text-dark); }
        .meta-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.025em; margin-bottom: 0.25rem; display: block; }
        .meta-value { font-size: 0.9375rem; font-weight: 500; color: var(--text-dark); }
        
        .separator { border-top: 1px dashed var(--border-color); margin: 1.5rem 0; }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 2!important;
        }
    </style>
@endsection

@section('content')
<div class="container py-5">
    @php
        $requestType = data_get($ticket->payload, 'request_type');
        $workflowStatus = data_get($ticket->payload, 'workflow_status', '-');
        $pendingApproval = $ticket->approvals->where('status', 'Pending')->first();
        $isBumRequest = in_array($requestType, ['consumption', 'atk_rtk', 'ga_request_finding'], true);
        $canManageGaOperations = \App\Support\GaAccess::allowed(auth()->user());
        $canUploadConsumptionEvidence = (int) $ticket->requester_id === (int) auth()->id() || $canManageGaOperations;
        $categoryLabel = match ($requestType) {
            'consumption' => 'Permintaan Konsumsi Rapat',
            'atk_rtk' => 'Permintaan ATK/RTK',
            'ga_request_finding' => 'GA Permintaan & Temuan',
            default => $ticket->category->name ?? '-',
        };
        $serviceLabel = match ($requestType) {
            'consumption' => 'Layanan BUM - Konsumsi Rapat',
            'atk_rtk' => 'Layanan BUM - ATK/RTK',
            'ga_request_finding' => 'Layanan BUM - QR Permintaan & Temuan',
            default => $ticket->categoryticket->name ?? '-',
        };
    @endphp

    {{-- ALERTS --}}
    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
            <i class="bi bi-check-circle-fill fs-5 me-2"></i> {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i> {{ session('error') }}
        </div>
    @endif

    {{-- HEADER --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-5 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-dark text-white px-2 py-1 rounded fw-mono">#{{ $ticket->ticket_no }}</span>
                @php
                    $statusColor = match($ticket->status->name) {
                        'Open' => 'success', 'In Progress' => 'warning', 'Closed' => 'dark', 'Resolved' => 'primary', default => 'secondary'
                    };
                @endphp
                <span class="badge bg-{{ $statusColor }} bg-opacity-10 text-{{ $statusColor }} border border-{{ $statusColor }} border-opacity-20 px-3 py-1 rounded-pill fw-semibold">
                    {{ $ticket->status->name }}
                </span>
            </div>
            <h1 class="page-title mb-0">{{ $ticket->title }}</h1>
        </div>
        <a href="{{ route('ticket.general') }}" class="btn-back text-decoration-none shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <div class="row g-4">
        
        {{-- ================= LEFT COLUMN ================= --}}
        <div class="col-lg-8">
            
            {{-- 1. DESKRIPSI --}}
            <div class="card-clean">
                <div class="card-header-clean">
                    <span class="header-title"><i class="bi bi-file-text text-danger"></i> Deskripsi</span>
                    <span class="text-muted small fw-medium">{{ $ticket->created_at->format('d M Y, H:i') }}</span>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4 pb-4 border-bottom border-light">
                        <div class="chat-avatar me-3 shadow-sm" style="background: var(--lrt-orange);">
                            {{ strtoupper(substr($ticket->requester->name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <div class="fw-bold text-dark">{{ $ticket->requester->name ?? 'Unknown' }}</div>
                            <div class="text-muted small">Pelapor / Requester</div>
                        </div>
                    </div>
                    <div class="text-dark opacity-90" style="line-height: 1.7; font-size: 0.95rem;">
                        {!! nl2br(e($ticket->description)) !!}
                    </div>
                </div>
            </div>

            @if(!empty($ticket->payload))
            <div class="card-clean">
                <div class="card-header-clean">
                    <span class="header-title"><i class="bi bi-ui-checks-grid text-danger"></i> Detail Request</span>
                    <span class="badge bg-light text-dark border">{{ data_get($ticket->payload, 'request_label', 'General') }}</span>
                </div>
                <div class="card-body p-4">
                    @php
                        if ($requestType === 'consumption') {
                            $detailPayload = collect([
                                'Nama Kegiatan' => data_get($ticket->payload, 'activity_name'),
                                'Jenis Kegiatan' => data_get($ticket->payload, 'event_type'),
                                'Tanggal' => data_get($ticket->payload, 'event_date'),
                                'Jam' => trim((data_get($ticket->payload, 'start_time') ?: '-') . ' - ' . (data_get($ticket->payload, 'end_time') ?: '-')),
                                'Ruang/Lokasi' => data_get($ticket->payload, 'location'),
                                'Jumlah Peserta' => data_get($ticket->payload, 'participant_count'),
                                'Jenis Konsumsi' => data_get($ticket->payload, 'consumption_type'),
                                'Atasan Approval' => data_get($ticket->payload, 'supervisor_name'),
                                'PIC Kegiatan' => data_get($ticket->payload, 'pic_contact'),
                                'Status BUM' => $workflowStatus,
                            ])->filter(fn ($value) => filled($value) && $value !== '- -');
                        } elseif ($requestType === 'atk_rtk') {
                            $detailPayload = collect([
                                'Judul Permintaan' => data_get($ticket->payload, 'request_subject'),
                                'Jenis Barang' => data_get($ticket->payload, 'item_type'),
                                'Total Qty Diminta' => data_get($ticket->payload, 'total_quantity', data_get($ticket->payload, 'quantity')),
                                'Total Estimasi' => data_get($ticket->payload, 'total_estimated_amount') ? 'Rp' . number_format((float) data_get($ticket->payload, 'total_estimated_amount'), 0, ',', '.') : null,
                                'Jumlah Disetujui' => data_get($ticket->payload, 'approved_qty'),
                                'Jumlah Diserahkan' => data_get($ticket->payload, 'fulfilled_qty'),
                                'Tanggal Dibutuhkan' => data_get($ticket->payload, 'needed_date'),
                                'Lokasi Pengiriman' => data_get($ticket->payload, 'delivery_location'),
                                'PIC Penerima' => data_get($ticket->payload, 'recipient_pic'),
                                'Atasan Approval' => data_get($ticket->payload, 'supervisor_name'),
                                'Status BUM' => $workflowStatus,
                            ])->filter(fn ($value) => filled($value));
                        } elseif ($requestType === 'ga_request_finding') {
                            $detailPayload = collect([
                                'Jenis Laporan' => data_get($ticket->payload, 'report_type'),
                                'Lokasi' => data_get($ticket->payload, 'location'),
                                'Detail Lokasi' => data_get($ticket->payload, 'detail_location'),
                                'Uraian' => data_get($ticket->payload, 'description'),
                                'Ekspektasi Tindak Lanjut' => data_get($ticket->payload, 'expected_action'),
                                'Kontak Pelapor' => data_get($ticket->payload, 'reporter_phone'),
                                'Status BUM' => $workflowStatus,
                            ])->filter(fn ($value) => filled($value));
                        } else {
                            $detailPayload = collect($ticket->payload)
                                ->except(['request_type', 'request_label', 'submitted_at', 'workflow'])
                                ->filter(fn ($value) => filled($value) && !is_array($value));
                        }
                    @endphp

                    @if($detailPayload->isNotEmpty())
                        <div class="row g-3">
                            @foreach($detailPayload as $key => $value)
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                        <div class="text-muted text-uppercase small fw-bold mb-1">{{ str_replace('_', ' ', $key) }}</div>
                                        <div class="text-dark fw-semibold">{{ $value }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($requestType === 'atk_rtk' && !empty(data_get($ticket->payload, 'items')))
                        <div class="mt-4">
                            <div class="text-muted text-uppercase small fw-bold mb-2">Daftar Barang</div>
                            <div class="table-responsive border rounded-3">
                                <table class="table mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Barang</th>
                                            <th class="text-end">Qty</th>
                                            <th class="text-end">Harga per UOM Kecil</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(data_get($ticket->payload, 'items', []) as $item)
                                            @php
                                                $stockItem = $consumableItems->firstWhere('id', data_get($item, 'item_id'));
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ data_get($item, 'item_name') }}</div>
                                                    <div class="text-muted small">{{ data_get($item, 'item_code') }}{{ data_get($item, 'small_uom') ? ' | ' . data_get($item, 'small_uom') : '' }}</div>
                                                </td>
                                                <td class="text-end">{{ number_format((int) data_get($item, 'quantity', 0)) }}</td>
                                                <td class="text-end">Rp{{ number_format((float) data_get($item, 'unit_price', 0), 0, ',', '.') }}</td>
                                                <td class="text-end fw-semibold">Rp{{ number_format((float) data_get($item, 'line_total', 0), 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if(!empty(data_get($ticket->payload, 'workflow')))
                        <div class="mt-4">
                            <div class="text-muted text-uppercase small fw-bold mb-2">Workflow</div>
                            <ol class="mb-0 ps-3 text-muted">
                                @foreach(data_get($ticket->payload, 'workflow', []) as $step)
                                    <li class="mb-2">{{ $step }}</li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            @if($ticket->attachments->isNotEmpty())
            <div class="card-clean">
                <div class="card-header-clean">
                    <span class="header-title"><i class="bi bi-paperclip text-danger"></i> Lampiran</span>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-20 rounded-pill px-2">{{ $ticket->attachments->count() }}</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        @foreach($ticket->attachments as $attachment)
                            <div class="col-md-6">
                                <a href="{{ asset('storage/' . $attachment->file_path) }}" target="_blank" class="text-decoration-none">
                                    <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div class="fw-semibold text-dark">{{ $attachment->file_name }}</div>
                                            <span class="badge bg-light text-dark border">{{ str_replace('_', ' ', $attachment->attachment_type ?? 'file') }}</span>
                                        </div>
                                        <div class="text-muted small">
                                            {{ strtoupper($attachment->mime_type ?? '-') }} • {{ number_format(($attachment->size ?? 0) / 1024, 1) }} KB
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- 2. DISKUSI --}}
            <div class="card-clean">
                <div class="card-header-clean">
                    <span class="header-title"><i class="bi bi-chat-square-text text-danger"></i> Diskusi</span>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-20 rounded-pill px-2">{{ $ticket->comments->count() }}</span>
                </div>
                <div class="card-body p-4">
                    <div class="chat-container mb-5">
                        @forelse($ticket->comments as $c)
                            @php $isMe = $c->user->id == auth()->id(); @endphp
                            <div class="chat-item {{ $isMe ? 'me' : '' }}">
                                <div class="chat-avatar shadow-sm" style="background-color: {{ $isMe ? 'var(--lrt-red)' : '#64748b' }}">
                                    {{ strtoupper(substr($c->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="chat-bubble">
                                        {{ $c->message }}
                                    </div>
                                    <div class="chat-meta">
                                        <span class="fw-semibold text-dark">{{ $isMe ? 'Anda' : $c->user->name }}</span> • {{ $c->created_at->diffForHumans() }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <div class="bg-light rounded-circle d-inline-flex p-3 mb-3 text-muted">
                                    <i class="bi bi-chat-dots fs-3"></i>
                                </div>
                                <p class="text-muted small mb-0">Belum ada diskusi. Jadilah yang pertama menanggapi.</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Form Komentar --}}
                    <div class="bg-light p-4 rounded-3 border border-light">
                        <form action="{{ route('ticket.comment', $ticket->id) }}" method="POST">
                            @csrf
                            <label class="form-label small fw-bold text-muted mb-2">BALAS TIKET</label>
                            <div class="d-flex gap-3">
                                <textarea name="message" class="form-control bg-white shadow-sm border-0" rows="2" placeholder="Tulis pesan atau update..." required style="resize: none;"></textarea>
                                
                            </div>
                            <button class="btn btn-primary shadow-sm mt-2 w-100">
                                    <i class="bi bi-send-fill fs-5"></i> Kirim Pesan
                                </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= RIGHT COLUMN (SIDEBAR) ================= --}}
        <div class="col-lg-4">
            
            {{-- 1. INFO PROPERTI --}}
            <div class="card-clean">
                <div class="card-header-clean">
                    <span class="header-title"><i class="bi bi-sliders text-danger"></i> Detail</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-6">
                            <span class="meta-label">Kategori</span>
                            <span class="meta-value">{{ $categoryLabel }}</span>
                        </div>
                        <div class="col-6">
                            <span class="meta-label">Layanan</span>
                            <span class="meta-value">{{ $serviceLabel }}</span>
                        </div>

                        @if($isBumRequest)
                        <div class="col-12">
                            <div class="border-bottom border-dashed"></div>
                        </div>
                        <div class="col-12">
                            <span class="meta-label">Status Proses</span>
                            <span class="badge bg-light text-dark border px-3 py-2">{{ $workflowStatus }}</span>
                        </div>
                        @else
                        <div class="col-12"><div class="border-bottom border-dashed"></div></div>
                        <div class="col-4">
                            <span class="meta-label">Priority</span>
                            <span class="fw-bold {{ ($ticket->priority->name ?? '') == 'High' ? 'text-danger' : 'text-dark' }}">
                                {{ $ticket->priority->name ?? '-' }}
                            </span>
                        </div>
                        <div class="col-4">
                            <span class="meta-label">Impact</span>
                            <span class="fw-bold {{ ($ticket->impact->name ?? '') == 'High' ? 'text-danger' : 'text-dark' }}">
                                {{ $ticket->impact->name ?? '-' }}
                            </span>
                        </div>
                        <div class="col-4">
                            <span class="meta-label">Urgency</span>
                            <span class="fw-bold text-dark">{{ $ticket->urgency->name ?? '-' }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 2. ADMIN PANEL (Simetris & Rapi) --}}
            @if(auth()->user()->role == 'admin')
            <div class="card-clean border-top-4 border-warning" style="border-top: 4px solid var(--lrt-orange);">
                <div class="card-header-clean bg-light">
                    <span class="header-title text-dark"><i class="bi bi-shield-lock-fill text-warning"></i> {{ $isBumRequest ? 'Kontrol Status' : 'Admin Zone' }}</span>
                </div>
                <div class="card-body p-4">
                    
                    <form action="{{ route('ticket.updateStatus', $ticket->id) }}" method="POST" class="mb-4">
                        @csrf @method('PUT')
                        <label class="meta-label mb-2">Update Status</label>
                        <div class="input-group shadow-sm">
                            <select name="status_id" class="form-select select2-bs5">
                                @foreach($statuses as $s)
                                    <option value="{{ $s->id }}" {{ $ticket->status_id==$s->id?'selected':'' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-primary w-100 mt-2">Update Status</button>
                    </form>

                    <div class="separator"></div>

                    @if(!$isBumRequest)
                    <form action="{{ route('ticket.assign', $ticket->id) }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-12 col-md-12">
                                <label class="meta-label mb-2">Assign Teknisi</label>
                                <select name="assigned_user_id" class="form-select select2-bs5" data-placeholder="Pilih Teknisi">
                                    <option value=""></option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" {{ $ticket->assigned_user_id==$u->id?'selected':'' }}>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-12">
                                <label class="meta-label mb-2">Assign Dept</label>
                                <select name="assigned_department_id" class="form-select select2-bs5" data-placeholder="Pilih Dept">
                                    <option value=""></option>
                                    @foreach($departments as $d)
                                        <option value="{{ $d->id }}" {{ $ticket->assigned_department_id==$d->id?'selected':'' }}>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary w-100 shadow-sm">
                                    Simpan Penugasan
                                </button>
                            </div>
                        </div>
                    </form>
                    @else
                        <div class="alert border-0 mb-0" style="background:#eef7fb; color:#0e5f74;">
                            Request ini diproses lewat alur BUM, jadi tidak memakai assign teknisi.
                        </div>
                    @endif
                </div>
            </div>
            @endif

            @if($pendingApproval && ((int) $pendingApproval->approver_id === (int) auth()->id() || auth()->user()->role === 'admin'))
            <div class="card-clean">
                <div class="card-header-clean">
                    <span class="header-title"><i class="bi bi-check2-square text-danger"></i> Approval Atasan</span>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('ticket.approve', $ticket) }}" method="POST" class="row g-3">
                        @csrf
                        <input type="hidden" name="approval_id" value="{{ $pendingApproval->id }}">
                        <div class="col-12">
                            <select name="status" class="form-select" required>
                                <option value="approved">Approve</option>
                                <option value="rejected">Reject</option>
                            </select>
                        </div>
                        <div class="col-12"><textarea name="note" class="form-control" rows="2" placeholder="Catatan approval"></textarea></div>
                        <div class="col-12"><button class="btn btn-primary w-100">Simpan Approval</button></div>
                    </form>
                </div>
            </div>
            @endif

            @if($requestType === 'atk_rtk')
            <div class="card-clean">
                <div class="card-header-clean">
                    <span class="header-title"><i class="bi bi-box-seam text-danger"></i> Proses ATK/RTK</span>
                    <span class="badge bg-light text-dark border">{{ $workflowStatus }}</span>
                </div>
                <div class="card-body p-4">
                    @if($canManageGaOperations)
                    <form action="{{ route('ticket.atk-rtk.bum-review', $ticket) }}" method="POST" class="row g-2 mb-4">
                        @csrf
                        <div class="col-12">
                            <label class="meta-label">Review BUM</label>
                            <select name="workflow_status" class="form-select">
                                <option value="STOCK_CHECKED">Stock Checked</option>
                                <option value="WAITING_PROCUREMENT">Waiting Procurement</option>
                                <option value="READY_TO_HANDOVER">Ready To Handover</option>
                                <option value="CANCELLED">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12"><input type="number" min="0" name="approved_qty" class="form-control" value="{{ data_get($ticket->payload, 'approved_qty', data_get($ticket->payload, 'quantity')) }}" placeholder="Qty approved"></div>
                        <div class="col-12"><textarea name="notes" class="form-control" rows="2" placeholder="Catatan BUM"></textarea></div>
                        <div class="col-12"><button class="btn btn-outline-primary w-100">Simpan Review</button></div>
                    </form>

                    @if(!empty(data_get($ticket->payload, 'items')))
                        <div class="border rounded-3 mb-4 overflow-hidden">
                            <div class="table-responsive">
                                <table class="table mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Barang</th>
                                            <th class="text-end">Diminta</th>
                                            <th class="text-end">Gudang Kecil</th>
                                            <th class="text-end">Gudang Besar</th>
                                            <th class="text-end">Rekomendasi</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(data_get($ticket->payload, 'items', []) as $row)
                                            @php
                                                $stockItem = $consumableItems->firstWhere('id', data_get($row, 'item_id'));
                                                $requestedQty = (int) data_get($row, 'quantity', 0);
                                                $smallStock = (int) ($stockItem->small_stock ?? 0);
                                                $bigStock = (int) ($stockItem->current_stock ?? 0);
                                                $conversionQty = max(1, (int) ($stockItem->conversion_qty ?? data_get($row, 'conversion_qty', 1)));
                                                $shortage = max(0, $requestedQty - $smallStock);
                                                $recommendedLargeQty = $shortage > 0 ? (int) ceil($shortage / $conversionQty) : 0;
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ data_get($row, 'item_name') }}</div>
                                                    <div class="text-muted small">1 {{ $stockItem->large_uom ?? data_get($row, 'large_uom') }} = {{ $conversionQty }} {{ $stockItem->small_uom ?? data_get($row, 'small_uom') }}</div>
                                                </td>
                                                <td class="text-end">{{ number_format($requestedQty) }} {{ $stockItem->small_uom ?? data_get($row, 'small_uom') }}</td>
                                                <td class="text-end">{{ number_format($smallStock) }} {{ $stockItem->small_uom ?? data_get($row, 'small_uom') }}</td>
                                                <td class="text-end">{{ number_format($bigStock) }} {{ $stockItem->large_uom ?? data_get($row, 'large_uom') }}</td>
                                                <td class="text-end">{{ $recommendedLargeQty ? number_format($recommendedLargeQty) . ' ' . ($stockItem->large_uom ?? data_get($row, 'large_uom')) : 'Cukup' }}</td>
                                                <td class="text-end">
                                                    @if($recommendedLargeQty > 0 && $bigStock > 0)
                                                        <form action="{{ route('ticket.atk-rtk.replenish', $ticket) }}" method="POST" class="d-inline-flex gap-1">
                                                            @csrf
                                                            <input type="hidden" name="item_id" value="{{ data_get($row, 'item_id') }}">
                                                            <input type="number" min="1" max="{{ $bigStock }}" name="large_qty" value="{{ min($recommendedLargeQty, max(1, $bigStock)) }}" class="form-control form-control-sm" style="width:76px;" required>
                                                            <button class="btn btn-sm btn-outline-danger">Ambil</button>
                                                        </form>
                                                    @elseif($recommendedLargeQty > 0)
                                                        <span class="badge bg-danger-subtle text-danger">Gudang Besar kosong</span>
                                                    @else
                                                        <span class="badge bg-success-subtle text-success">Siap</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <form action="{{ route('ticket.atk-rtk.handover', $ticket) }}" method="POST" class="row g-2">
                            @csrf
                            <div class="col-12"><input name="received_by" class="form-control" value="{{ $ticket->requester->name ?? '' }}" placeholder="Diterima oleh" required></div>
                            <div class="col-12"><textarea name="notes" class="form-control" rows="2" placeholder="Catatan serah terima"></textarea></div>
                            <div class="col-12"><button class="btn btn-primary w-100">Handover Semua & Kurangi Gudang Kecil</button></div>
                        </form>
                    @else
                        <form action="{{ route('ticket.atk-rtk.handover', $ticket) }}" method="POST" class="row g-2">
                            @csrf
                            <div class="col-12">
                                <label class="meta-label">Handover Barang</label>
                                <select name="item_id" class="form-select select2-bs5" data-placeholder="Cari item stok" required>
                                    <option value="">Pilih item stok</option>
                                    @foreach($consumableItems as $item)
                                        <option value="{{ $item->id }}" @selected(data_get($ticket->payload, 'item_id') == $item->id)>{{ $item->code }} - {{ $item->name }} (gudang kecil {{ $item->small_stock }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6"><input type="number" min="1" name="fulfilled_qty" class="form-control" value="{{ data_get($ticket->payload, 'approved_qty', data_get($ticket->payload, 'quantity', 1)) }}" required></div>
                            <div class="col-6"><input name="received_by" class="form-control" value="{{ $ticket->requester->name ?? '' }}" placeholder="Diterima oleh" required></div>
                            <div class="col-12"><textarea name="notes" class="form-control" rows="2" placeholder="Catatan serah terima"></textarea></div>
                            <div class="col-12"><button class="btn btn-primary w-100">Handover & Kurangi Gudang Kecil</button></div>
                        </form>
                    @endif
                    @else
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="meta-label">Status Proses</span>
                            <div class="meta-value">{{ $workflowStatus }}</div>
                        </div>
                        <div class="col-md-6">
                            <span class="meta-label">Qty Disetujui</span>
                            <div class="meta-value">{{ data_get($ticket->payload, 'approved_qty', data_get($ticket->payload, 'quantity', '-')) }}</div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            @if($requestType === 'consumption')
            <div class="card-clean">
                <div class="card-header-clean">
                    <span class="header-title"><i class="bi bi-cup-hot text-danger"></i> Proses Konsumsi</span>
                    <span class="badge bg-light text-dark border">{{ $workflowStatus }}</span>
                </div>
                <div class="card-body p-4">
                    @if($canManageGaOperations)
                    <form action="{{ route('ticket.consumption.flow', $ticket) }}" method="POST" class="row g-2 mb-4">
                        @csrf
                        <div class="col-12">
                            <label class="meta-label">Status Proses</label>
                            <select name="workflow_status" class="form-select">
                                @foreach(['APPROVED_BY_BUM', 'ORDERED_TO_VENDOR', 'RECEIVED', 'WAITING_ACCOUNTABILITY', 'REPORTED', 'CLOSED', 'CANCELLED'] as $statusOption)
                                    <option value="{{ $statusOption }}" @selected($workflowStatus === $statusOption)>{{ $statusOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12"><input name="vendor_name" class="form-control" value="{{ data_get($ticket->payload, 'vendor_name') }}" placeholder="Vendor"></div>
                        <div class="col-6"><input type="date" name="order_date" class="form-control" value="{{ data_get($ticket->payload, 'order_date') }}"></div>
                        <div class="col-6"><input type="date" name="receipt_date" class="form-control" value="{{ data_get($ticket->payload, 'receipt_date') }}"></div>
                        <div class="col-6"><input type="number" min="0" name="estimated_cost" class="form-control" value="{{ data_get($ticket->payload, 'estimated_cost') }}" placeholder="Estimasi"></div>
                        <div class="col-6"><input type="number" min="0" name="actual_cost" class="form-control" value="{{ data_get($ticket->payload, 'actual_cost') }}" placeholder="Aktual"></div>
                        <div class="col-12"><textarea name="notes" class="form-control" rows="2" placeholder="Catatan order/verifikasi">{{ data_get($ticket->payload, 'notes') }}</textarea></div>
                        <div class="col-12"><button class="btn btn-outline-primary w-100">Update Konsumsi</button></div>
                    </form>
                    @else
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <span class="meta-label">Vendor</span>
                            <div class="meta-value">{{ data_get($ticket->payload, 'vendor_name', '-') ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <span class="meta-label">Tanggal Pesanan</span>
                            <div class="meta-value">{{ data_get($ticket->payload, 'order_date', '-') ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <span class="meta-label">Tanggal Diterima</span>
                            <div class="meta-value">{{ data_get($ticket->payload, 'receipt_date', '-') ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <span class="meta-label">Biaya Aktual</span>
                            <div class="meta-value">{{ data_get($ticket->payload, 'actual_cost') ? 'Rp' . number_format((float) data_get($ticket->payload, 'actual_cost'), 0, ',', '.') : '-' }}</div>
                        </div>
                    </div>
                    @endif

                    @if($canUploadConsumptionEvidence)
                    <form action="{{ route('ticket.consumption.evidence', $ticket) }}" method="POST" enctype="multipart/form-data" class="row g-2">
                        @csrf
                        <div class="col-12"><label class="meta-label">Upload Pertanggungjawaban</label></div>
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1">Daftar hadir peserta</label>
                            <input type="file" name="attendance_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1">Dokumentasi kegiatan</label>
                            <input type="file" name="documentation_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.zip,.rar">
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1">Laporan kegiatan</label>
                            <input type="file" name="activity_report_file" class="form-control" accept=".pdf,.doc,.docx">
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1">Materi training / pendukung</label>
                            <input type="file" name="training_material_file" class="form-control" accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx">
                        </div>
                        <div class="col-12"><button class="btn btn-primary w-100">Upload Pertanggungjawaban</button></div>
                    </form>
                    @endif
                </div>
            </div>
            @endif

            {{-- 3. TIMELINE (Tailwind Style) --}}
            <div class="card-clean">
                <div class="card-header-clean">
                    <span class="header-title"><i class="bi bi-clock-history text-danger"></i> Riwayat</span>
                </div>
                <div class="card-body p-4">
                    <div class="timeline-modern mt-2">
                        @foreach($ticket->histories as $history)
                            <div class="timeline-item">
                                <div class="timeline-marker {{ $history->user_id ? '' : 'system' }}"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="timeline-user">{{ $history->user->name ?? 'System' }}</span>
                                        <span class="timeline-time">{{ $history->created_at->format('d M, H:i') }}</span>
                                    </div>
                                    
                                    {{-- Action Box --}}
                                    <div class="timeline-action">
                                        {{ $history->action }}
                                        @if($history->status)
                                            <div class="d-flex align-items-center mt-1 text-muted small">
                                                <i class="bi bi-arrow-right me-1"></i> 
                                                <span class="fw-bold text-dark">{{ $history->status->name }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2-bs5').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: function() { return $(this).data('placeholder'); }
        });
    });
</script>
@endsection
