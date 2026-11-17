@extends('partials.layouts.master')

@section('title', 'Perjalanan Dinas | Satset')
@section('pagetitle', 'Perjalanan Dinas')

@section('content')
<div class="container-fluid">
    @unless($acceptingRequests)
        <div class="alert alert-light border" role="status">
            <strong>Pengajuan SPPD baru sedang ditutup.</strong> Pengajuan yang sudah tercatat dapat dipantau dari halaman ini.
        </div>
    @endunless
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h4 class="mb-0">{{ $isOperator ? 'Semua Pengajuan SPPD' : 'Pengajuan SPPD Saya' }}</h4>
            <div class="d-flex flex-wrap gap-2">
                @can('configure-sppd')
                    <a href="{{ route('settings.sppd-service.index') }}" class="btn btn-outline-secondary">Status SPPD</a>
                @endcan
                @if($acceptingRequests)
                    <a href="{{ route('sppd.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Ajukan SPPD</a>
                @endif
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Pengajuan</th>@if($isOperator)<th>Pemohon</th>@endif<th>Tujuan</th><th>Berangkat</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($requests as $ticket)
                        <tr>
                            <td>{{ \App\Support\Sppd::reference($ticket) }}</td>
                            @if($isOperator)
                                <td>{{ data_get($ticket->payload, 'sppd.profile.name') }}</td>
                            @endif
                            <td>{{ data_get($ticket->payload, 'sppd.destination') }}</td>
                            <td>{{ data_get($ticket->payload, 'sppd.depart_on') }}</td>
                            <td>{{ \App\Support\Sppd::label($ticket) }}</td>
                            <td class="text-end"><a href="{{ route('sppd.show', $ticket) }}" class="btn btn-sm btn-outline-primary">Lihat Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isOperator ? 6 : 5 }}" class="text-center text-muted py-4">Belum ada pengajuan perjalanan dinas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
@endsection
