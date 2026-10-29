@extends('partials.layouts.master')
@section('title',isset($ticket)?'Revisi Permintaan IT':'Buat Tiket IT')
@section('pagetitle','Permintaan IT')
@section('title-sub','Layanan IT')
@section('content')
<div class="container-fluid"><div class="card"><div class="card-body">
<a href="{{ route('it.index') }}" class="btn btn-light mb-3">Layanan IT</a><h1 class="h4">{{ isset($ticket)?'Revisi permintaan IT':'Buat tiket IT' }}</h1>
<p class="text-muted">Pilih layanan dan isi kebutuhan Anda. Jangan menyertakan kata sandi, token, atau kredensial.</p>
<div id="it-request-feedback" role="status"></div>
<form id="it-request-form" method="post" enctype="multipart/form-data" action="{{ isset($ticket) ? route('it.action',[$ticket,'revise']) : route('it.store') }}" data-schema="{{ isset($ticket) ? route('it.revise.schema',$ticket) : route('it.form') }}">@csrf
@if(isset($ticket))<input type="hidden" name="lock_version" value="{{ $ticket->itDetail->lock_version }}">@else<input type="hidden" name="submission_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">@endif
<div class="row g-3"><div class="col-md-6"><label class="form-label" for="it-catalog">Layanan</label><select name="catalog_key" id="it-catalog" class="form-select" @disabled(isset($ticket))>@foreach($catalog as $item)<option value="{{ $item['key'] }}">{{ $item['name'] }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label" for="it-title">Judul</label><input id="it-title" name="title" class="form-control" maxlength="150" value="{{ $ticket->title ?? '' }}" required></div>
<div class="col-12"><label class="form-label" for="it-description">Ringkasan kebutuhan / gangguan</label><textarea id="it-description" name="description" class="form-control" rows="3" maxlength="10000" required>{{ $ticket->description ?? '' }}</textarea></div>
@if(!isset($ticket))@foreach(['impact'=>'Dampak terhadap pekerjaan','urgency'=>'Urgensi kebutuhan'] as $key=>$name)<div class="col-md-6"><label class="form-label" for="it-{{ $key }}">{{ $name }}</label><select id="it-{{ $key }}" name="{{ $key }}" class="form-select"><option value="low">Rendah</option><option value="medium" selected>Sedang</option><option value="high">Tinggi</option></select></div>@endforeach
@else<div class="col-12"><label class="form-label">Alasan revisi</label><textarea name="reason" class="form-control" minlength="5" maxlength="5000" required></textarea></div>@endif
</div><hr><div id="it-request-fields" class="row g-3"></div><button class="btn btn-primary mt-4" id="it-request-submit">{{ isset($ticket)?'Kirim ulang untuk approval':'Kirim permintaan' }}</button>
</form><script type="application/json" id="it-request-initial">@json($answers ?? [])</script>
</div></div></div>
@endsection
@section('js')<script src="{{ asset('assets/js/it/request-form.js') }}"></script>@endsection
