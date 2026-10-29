@extends('partials.layouts.master')
@section('title','Persetujuan IT')
@section('pagetitle','Persetujuan IT')
@section('title-sub','Layanan IT')
@section('content')
<div class="container-fluid"><div class="card"><div class="card-body"><a href="{{ route('it.index') }}" class="btn btn-light mb-3">Layanan IT</a><h1 class="h4">Persetujuan Saya · IT</h1>@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="table-responsive"><table class="table"><thead><tr><th>Tiket</th><th>Judul</th><th>Status</th><th>Revisi</th></tr></thead><tbody>@forelse($approvals as $a)<tr><td><a href="{{ route('it.approval',$a->id) }}">{{ $a->ticket_no }}</a></td><td>{{ $a->title }}</td><td>{{ $a->status }}</td><td>{{ $a->cycle }}</td></tr>@empty<tr><td colspan="4" class="text-muted">Tidak ada persetujuan IT untuk Anda.</td></tr>@endforelse</tbody></table></div>{{ $approvals->links() }}</div></div></div>
@endsection
