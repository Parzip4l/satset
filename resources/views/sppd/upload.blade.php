<div>
    <label class="form-label" for="sppd-upload-{{ $key }}">{{ $label }}</label>
    <input class="form-control" id="sppd-upload-{{ $key }}" type="file" name="uploads[{{ $key }}]" accept="{{ $accept }}">
    @if($ticket && ($name = data_get($sppd, "attachments.{$key}.name")))
        <p class="sppd-help mt-1">Tersimpan <a href="{{ route('sppd.attachment', [$ticket, $key]) }}">{{ $name }}</a></p>
    @endif
</div>
