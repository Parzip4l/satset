@extends('partials.layouts.master')

@section('title', 'Approval Saya | SatSet System')
@section('title-sub', 'Request Approval')
@section('pagetitle', 'Approval Saya')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div>
                            <h4 class="fw-bold mb-1">Approval Saya</h4>
                            <p class="text-muted mb-0">Daftar request yang membutuhkan keputusan atasan.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning-subtle text-warning px-3 py-2 rounded-pill">
                                Pending: {{ $pendingCount }}
                            </span>
                            <a href="{{ route('ticket.index') }}" class="btn btn-light border shadow-sm px-4">
                                <i class="bi bi-grid me-1"></i> Menu Requests
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 mb-4 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form method="GET" action="{{ route('ticket.approvals') }}">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-4">
                                <select class="form-select" name="approval_status" onchange="this.form.submit()">
                                    <option value="Pending" @selected($statusFilter === 'Pending')>Pending</option>
                                    <option value="approved" @selected($statusFilter === 'approved')>Approved</option>
                                    <option value="rejected" @selected($statusFilter === 'rejected')>Rejected</option>
                                    <option value="all" @selected($statusFilter === 'all')>Semua Approval</option>
                                </select>
                            </div>
                            <div class="col-md-2 ms-auto">
                                <a href="{{ route('ticket.approvals') }}" class="btn btn-outline-danger w-100">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted text-uppercase fs-11 fw-bold">
                                <tr>
                                    <th class="ps-4 py-3">Request</th>
                                    <th class="py-3">Pemohon</th>
                                    <th class="py-3">Nilai Estimasi</th>
                                    <th class="py-3">Status Approval</th>
                                    <th class="py-3">Dibuat</th>
                                    <th class="text-end pe-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($approvals as $approval)
                                    @php
                                        $ticket = $approval->request;
                                        $workflowStatus = data_get($ticket->payload, 'workflow_status', '-');
                                        $approvalColor = match($approval->status) {
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            default => 'warning',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="fw-bold text-dark">{{ $ticket->title }}</div>
                                            <div class="d-flex flex-wrap gap-2 mt-1">
                                                <small class="text-muted fw-medium">{{ $ticket->ticket_no }}</small>
                                                <span class="badge bg-light text-dark border">{{ data_get($ticket->payload, 'request_label', 'Request') }}</span>
                                                <span class="badge bg-light text-dark border">{{ $workflowStatus }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $ticket->requester->name ?? '-' }}</div>
                                            <small class="text-muted">{{ $ticket->requester->email ?? '-' }}</small>
                                        </td>
                                        <td>
                                            {{ data_get($ticket->payload, 'total_estimated_amount') ? 'Rp' . number_format((float) data_get($ticket->payload, 'total_estimated_amount'), 0, ',', '.') : '-' }}
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $approvalColor }}-subtle text-{{ $approvalColor }} px-3 py-2 rounded-pill">
                                                {{ strtoupper($approval->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="text-muted fs-12">
                                                <span class="text-dark fw-medium">{{ $approval->created_at->format('d M Y') }}</span><br>
                                                <span>{{ $approval->created_at->format('H:i') }} WIB</span>
                                            </div>
                                        </td>
                                        <td class="text-end pe-4">
                                            @if($approval->status === 'Pending')
                                                <form action="{{ route('ticket.approve', $ticket) }}" method="POST" class="d-flex flex-column gap-2 align-items-end">
                                                    @csrf
                                                    <input type="hidden" name="approval_id" value="{{ $approval->id }}">
                                                    <textarea name="note" class="form-control form-control-sm" rows="2" placeholder="Catatan atasan" style="min-width: 220px;"></textarea>
                                                    <div class="d-flex gap-2">
                                                        <a href="{{ route('ticket.show', $ticket->id) }}" class="btn btn-sm btn-light border">
                                                            Detail
                                                        </a>
                                                        <button type="submit" name="status" value="rejected" class="btn btn-sm btn-outline-danger">
                                                            Reject
                                                        </button>
                                                        <button type="submit" name="status" value="approved" class="btn btn-sm btn-success">
                                                            Approve
                                                        </button>
                                                    </div>
                                                </form>
                                            @else
                                                <a href="{{ route('ticket.show', $ticket->id) }}" class="btn btn-sm btn-primary">
                                                    Lihat Detail
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="mb-3">
                                                <i class="bi bi-check2-circle fs-1 text-muted opacity-25"></i>
                                            </div>
                                            <h6 class="text-muted fw-normal">Tidak ada approval ditemukan.</h6>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-top py-3">
                    {{ $approvals->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
