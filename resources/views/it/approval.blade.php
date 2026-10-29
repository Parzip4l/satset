@extends('partials.layouts.master')
@section('title','Keputusan IT')
@section('pagetitle','Keputusan IT')
@section('title-sub','Layanan IT')
@section('content')
<div class="container-fluid"><div class="card"><div class="card-body"><a href="{{ route('it.approvals') }}" class="btn btn-light mb-3">Persetujuan Saya</a><h1 class="h4">{{ $detail['ticket_no'] }} · {{ $detail['title'] }}</h1><p>{{ $detail['requester'] }} · {{ $detail['status'] }} · Revisi {{ $detail['cycle'] }}</p><p style="white-space:pre-wrap">{{ $detail['description'] }}</p>
<dl class="row">@foreach($detail['answers'] as $key=>$value)<dt class="col-sm-3">{{ $key }}</dt><dd class="col-sm-9">{{ is_array($value) ? implode(', ',$value) : $value }}</dd>@endforeach</dl>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if($detail['status']==='pending')<form method="post" action="{{ route('it.decide',$detail['id']) }}">@csrf<input type="hidden" name="lock_version" value="{{ $detail['lock_version'] }}"><label class="form-label">Keputusan</label><select name="decision" class="form-select mb-3"><option value="approved">Setujui</option><option value="revision">Minta revisi</option><option value="rejected">Tolak</option></select><label class="form-label">Alasan</label><textarea name="reason" class="form-control mb-3" minlength="5" maxlength="5000" required></textarea><button class="btn btn-primary">Simpan keputusan</button></form>@else<p>{{ $detail['reason'] }}</p>@endif
</div></div></div>
@endsection
