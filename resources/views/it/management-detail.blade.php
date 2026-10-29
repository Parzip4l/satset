@extends('partials.layouts.master')
@section('title','Rincian Dashboard IT')
@section('pagetitle','Rincian Dashboard IT')
@section('title-sub','Layanan IT')
@section('css') @include('it.configuration.style') @endsection
@section('content')
@php($titles=['incoming'=>'Tiket masuk','resolved'=>'Tiket diselesaikan','backlog'=>'Backlog aktif','near'=>'Mendekati SLA','breached'=>'Melewati SLA'])
<div class="container-fluid it-config">
    <header class="config-header config-page-header"><div class="config-page-heading"><div class="config-eyebrow">Dashboard Manajemen IT</div><h1>{{ $titles[$metric] }}</h1><p class="mb-0">{{ $tickets->total() }} tiket sesuai filter</p></div><nav class="config-page-actions"><a class="config-page-action config-page-action-back" href="{{ route('it.dashboard', collect($filters)->except(['detail','export'])->all()) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Kembali ke dashboard</a></nav></header>
    <div class="config-note"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>@if(in_array($metric,['incoming','resolved']))Periode {{ $filters['from'] }} hingga {{ $filters['to'] }} berdasarkan {{ $metric === 'incoming' ? 'tanggal pembuatan tiket' : 'event penyelesaian' }}.@else Kondisi saat ini. Filter tanggal tidak membatasi daftar ini.@endif Tim, PIC, prioritas, layanan, kategori, dan status menggunakan data saat ini.</div>
    <div class="config-panel"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Tiket</th><th>Jenis</th><th>Status</th><th>Prioritas</th><th>Tim</th><th>PIC</th><th>Masuk</th></tr></thead><tbody>@forelse($tickets as $ticket)<tr><td><a href="{{ route('it.show',$ticket) }}">{{ $ticket->ticket_no }}</a><div class="config-small">{{ \Illuminate\Support\Str::limit($ticket->title,65) }}</div></td><td>{{ $ticket->itDetail->record_type === 'incident' ? 'Incident' : 'Service Request' }}</td><td>{{ ucwords(str_replace('_',' ',$ticket->itDetail->status)) }}</td><td>{{ $ticket->itDetail->priority ?? '—' }}</td><td>{{ $ticket->itDetail->supportTeam?->name ?? 'Belum ditugaskan' }}</td><td>{{ $ticket->assignedUser?->name ?? 'Belum ditugaskan' }}</td><td>{{ $ticket->created_at->copy()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="7" class="config-small">Tidak ada tiket untuk pilihan ini.</td></tr>@endforelse</tbody></table></div>{{ $tickets->links() }}</div>
</div>
@endsection
