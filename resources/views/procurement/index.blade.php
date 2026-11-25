@extends('partials.layouts.master')
@section('title', 'Antrean Procurement')
@section('pagetitle', 'Antrean Procurement')
@section('title-sub', 'Pengadaan Barang')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap gap-3 justify-content-between mb-4"><div><h1 class="h3">Antrean Procurement</h1><p class="text-muted">Pantau persetujuan dan penuhi kebutuhan barang atau dana beserta bukti penyerahan.</p></div><div class="d-flex gap-2 align-items-start"><a href="{{ route('procurement.reports') }}" class="btn btn-outline-primary">Laporan Manajemen</a><a href="{{ route('procurement.create') }}" class="btn btn-primary">Ajukan Pengadaan</a></div></div>
    <div class="card"><div class="card-body">
        <form class="d-flex gap-2 mb-4" method="GET"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari nomor atau kebutuhan" aria-label="Cari pengajuan"><button class="btn btn-outline-primary">Cari</button><a href="{{ route('procurement.index') }}" class="btn btn-outline-secondary">Reset</a></form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Pengajuan</th><th>Pemohon / Departemen</th><th>Perkiraan anggaran</th><th>Progres</th><th></th></tr></thead><tbody>
        @forelse($tickets as $ticket)
            @php($progress = \App\Support\TicketWorkflowPresenter::make($ticket->payload))
            <tr><td><a href="{{ route('ticket.show', $ticket) }}">{{ $ticket->ticket_no }}</a><div class="small text-muted">{{ $ticket->title }}</div></td><td>{{ data_get($ticket->payload, 'requester_name', $ticket->requester?->name) }}<div class="small text-muted">{{ data_get($ticket->payload, 'requester_department') }}</div></td><td>Rp{{ number_format(data_get($ticket->payload, 'total_estimated_amount', 0), 2, ',', '.') }}</td><td><span class="badge bg-{{ $progress['color'] }}">{{ $progress['label'] }}</span></td><td><a class="btn btn-outline-secondary btn-sm" href="{{ route('ticket.show', $ticket) }}">Detail</a></td></tr>
        @empty<tr><td colspan="5" class="text-center text-muted py-5">Belum ada pengajuan pengadaan barang.</td></tr>@endforelse
        </tbody></table></div>{{ $tickets->links() }}
    </div></div>
</div>
@endsection
