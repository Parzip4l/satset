@extends('partials.layouts.master')

@section('title', 'Detail SPPD | Satset')
@section('pagetitle', 'Detail Pengajuan SPPD')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('sppd.index') }}" class="btn btn-light border">Perjalanan Dinas</a>
        @if(! \App\Support\SppdAccess::isOperator(auth()->user()) && $approvals->contains(fn ($approval) => (int) $approval->approver_id === (int) auth()->id()))
            <a href="{{ route('ticket.approvals') }}" class="btn btn-light border">Approval Saya</a>
        @endif
    </div>
    @include('sppd.messages')

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h4 class="mb-1">{{ \App\Support\Sppd::reference($ticket) }}</h4>
                <p class="mb-0">{{ \App\Support\Sppd::label($ticket) }}</p>
            </div>
            @if(data_get($ticket->payload, 'workflow_status') === 'ISSUED')
                <a href="{{ route('sppd.pdf', $ticket) }}" class="btn btn-primary"><i class="bi bi-download me-1"></i> Unduh PDF SPPD</a>
            @elseif(data_get($ticket->payload, 'workflow_status') === 'REVISION_REQUESTED' && (int) $ticket->requester_id === (int) auth()->id())
                <a href="{{ route('sppd.edit', $ticket) }}" class="btn btn-primary">Revisi Pengajuan</a>
            @endif
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                @foreach([
                    'Pelaksana' => $sppd['profile']['name'],
                    'NIK' => $sppd['profile']['nik'],
                    'Jabatan' => $sppd['profile']['position'],
                    'Divisi' => $sppd['profile']['division'],
                    'Direktorat' => $sppd['profile']['directorate'],
                    'Kategori' => \App\Support\Sppd::CATEGORIES[$sppd['category']],
                    'Dari' => $sppd['origin'],
                    'Ke' => $sppd['destination'],
                    'Tanggal Berangkat' => $sppd['depart_on'],
                    'Tanggal Kembali' => $sppd['return_on'],
                    'Jumlah Hari' => $sppd['days'],
                    'Keperluan' => $sppd['purpose'],
                ] as $label => $value)
                    <dt class="col-sm-3">{{ $label }}</dt><dd class="col-sm-9 text-break" style="white-space: pre-line">{{ $value }}</dd>
                @endforeach
            </dl>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Rencana Anggaran Biaya Perjalanan Dinas</h5></div>
        <div class="table-responsive">@include('sppd.budget', ['tableClass' => 'table align-middle mb-0'])</div>
        @if(filled($sppd['notes']) || filled(data_get($sppd, 'bank.account')))
            <div class="card-body">
                <h6>Catatan Tambahan</h6>
                @include('sppd.notes')
            </div>
        @endif
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Tanda Tangan dan Persetujuan</h5></div>
        <div class="card-body">
            <ol class="list-group list-group-numbered">
                @foreach(\App\Support\Sppd::STAGES as $level => [$action, $role])
                    @php
                        $approval = $approvals->get(data_get($sppd, "approvals.{$level}"));
                        $status = $approval?->status;
                        $url = $level === 0 ? data_get($sppd, 'requester_signature.portal_signature_url') : $approval?->portal_signature_url;
                        $canDecide = $status === 'Pending' && (int) $approval->approver_id === (int) auth()->id();
                    @endphp
                    <li class="list-group-item">
                        <div class="d-inline-block align-top ms-2">
                            <div class="fw-semibold">{{ $action }} · {{ $role }}</div>
                            <div class="text-muted">{{ $level === 0 ? $sppd['profile']['name'] : $approval?->approver?->name }}</div>
                            <div>{{ $level === 0 ? 'Diajukan' : match($status) {
                                'Pending' => 'Menunggu tindakan',
                                'approved' => $level === 3 ? 'Disetujui' : 'Diketahui',
                                'revision_requested' => 'Meminta revisi',
                                'rejected' => 'Ditolak',
                                default => 'Belum diproses',
                            } }}</div>
                            @if($approval?->notes)
                                <p class="mb-1 text-break" style="white-space: pre-line">{{ $approval->notes }}</p>
                            @endif
                            @if($url)
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="small">Verifikasi tanda tangan di Portal</a>
                            @endif
                        </div>
                        @if($canDecide)
                            <form action="{{ route('sppd.decide', [$ticket, $approval]) }}" method="POST" class="mt-3">
                                @csrf
                                <label class="form-label" for="approval-note">Catatan keputusan</label>
                                <textarea class="form-control mb-2" id="approval-note" name="note" rows="2" maxlength="1000">{{ old('note') }}</textarea>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-success" name="decision" value="approved">{{ $level === 3 ? 'Setujui dan Terbitkan' : 'Konfirmasi Mengetahui' }}</button>
                                    <button class="btn btn-outline-secondary" name="decision" value="revision_requested">Minta Revisi</button>
                                    <button class="btn btn-outline-danger" name="decision" value="rejected">Tolak</button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Riwayat</h5></div>
        <ul class="list-group list-group-flush">
            @foreach($history as $entry)
                <li class="list-group-item">
                    {{ $entry->action }}
                    <div class="small text-muted">{{ $entry->user?->name }} · {{ $entry->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</div>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
