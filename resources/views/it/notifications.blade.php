@extends('partials.layouts.master')
@section('title','Notifikasi IT')
@section('pagetitle','Notifikasi IT')
@section('title-sub','Layanan IT')
@section('content')
<div class="container-fluid"><div class="card"><div class="card-body"><a href="{{ route('it.index') }}" class="btn btn-light mb-3">Layanan IT</a><h1 class="h4">Notifikasi IT</h1>@forelse($data as $item)<div class="border-bottom py-3 d-flex justify-content-between gap-2"><div><a href="{{ $item['url'] }}">{{ $item['ticket_no'] }}</a><p class="mb-0">{{ $item['message'] }}</p><small class="text-muted">{{ $item['created_at'] }}</small></div>@if(!$item['read_at'])<form method="post" action="{{ $item['read_url'] ?? route('it.notifications.read',$item['id']) }}">@csrf<button class="btn btn-light btn-sm">Tandai dibaca</button></form>@endif</div>@empty<p class="text-muted">Belum ada notifikasi IT.</p>@endforelse</div></div></div>
@endsection
