<div class="separator"></div>
<h3 class="h6">Rincian Pengadaan Barang</h3>
<div class="table-responsive"><table class="table table-sm align-middle" style="min-width:760px"><thead><tr><th>No.</th><th>Kebutuhan / Deskripsi</th><th>Lokasi</th><th>Qty</th><th>Terpenuhi</th><th>Anggaran per baris</th><th>Status</th></tr></thead><tbody>
@foreach(data_get($ticket->payload, 'items', []) as $line => $item)
<tr><td>{{ $line + 1 }}</td><td>{{ $item['need'] }}<div class="small text-muted" style="white-space:pre-wrap">{{ $item['description'] }}</div></td><td>{{ $item['location'] }}</td><td>{{ $item['quantity'] }}</td><td>{{ $item['fulfilled_quantity'] ?? 0 }}</td><td>Rp{{ number_format($item['estimated_budget'], 2, ',', '.') }}</td><td>{{ ['FULFILLED' => 'Terpenuhi', 'PARTIAL' => 'Sebagian', 'WAITING' => 'Menunggu'][$item['fulfillment_status'] ?? ''] ?? 'Belum dipenuhi' }}</td></tr>
@endforeach
</tbody><tfoot><tr><th colspan="5">Total perkiraan anggaran</th><th colspan="2">Rp{{ number_format(data_get($ticket->payload, 'total_estimated_amount', 0), 2, ',', '.') }}</th></tr></tfoot></table></div>
@if(\App\Support\ProcurementAccess::allowed(auth()->user()))
    @if(in_array($workflowStatus, ['WAITING_PROCUREMENT_REVIEW', 'WAITING_PROCUREMENT', 'PARTIALLY_FULFILLED']))
    <div class="border rounded p-3 mt-3">
        <h3 class="h6">Tindak lanjut Procurement</h3><p class="small text-muted">Penuhi satu baris sekaligus atau catat alasan menunggu. Qty dana menunjukkan jumlah unit kebutuhan yang dicakup oleh dana tersebut. Penyerahan stok memakai satuan kecil gudang.</p>
        <form action="{{ route('procurement.fulfill', $ticket) }}" method="POST" enctype="multipart/form-data" class="row g-3" id="procurement-fulfillment">
            @csrf
            <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="col-md-6"><label for="fulfill-line" class="form-label">Baris kebutuhan</label><select name="line" id="fulfill-line" class="form-select" required>
            @foreach(data_get($ticket->payload, 'items', []) as $line => $item)
            @if(($item['fulfilled_quantity'] ?? 0) < $item['quantity'])<option value="{{ $line }}" @selected((string)old('line') === (string)$line)>{{ $line + 1 }}. {{ $item['need'] }} — sisa {{ $item['quantity'] - ($item['fulfilled_quantity'] ?? 0) }}</option>@endif
            @endforeach</select></div>
            <div class="col-md-6"><label for="fulfill-method" class="form-label">Tindak lanjut</label><select id="fulfill-method" name="method" class="form-select" required><option value="waiting" @selected(old('method') === 'waiting')>Menunggu barang / dana</option><option value="stock" @selected(old('method') === 'stock')>Serahkan barang dari stok</option><option value="funds" @selected(old('method') === 'funds')>Berikan dana</option></select></div>
            <div class="col-12" data-fulfill="stock"><label for="fulfill-stock" class="form-label">Barang gudang (satuan kecil)</label><select id="fulfill-stock" name="item_id" class="form-select"><option value="">Pilih barang yang sesuai kebutuhan</option>@foreach($consumableItems as $stockItem)<option value="{{ $stockItem->id }}" @selected(old('item_id') == $stockItem->id)>{{ $stockItem->code }} — {{ $stockItem->name }} (stok: {{ $stockItem->small_stock }} {{ $stockItem->small_uom }})</option>@endforeach</select></div>
            <div class="col-md-6" data-fulfill="delivery"><label for="fulfill-quantity" class="form-label">Qty yang dipenuhi</label><input type="number" name="quantity" id="fulfill-quantity" class="form-control" min="1" max="1000000" value="{{ old('quantity', 1) }}"></div>
            <div class="col-md-6" data-fulfill="funds"><label for="fulfill-amount" class="form-label">Dana diberikan (Rp)</label><input type="number" name="amount" id="fulfill-amount" class="form-control" step="0.01" min="0.01" max="999999999999.99" value="{{ old('amount') }}"></div>
            <div class="col-md-6" data-fulfill="delivery"><label for="fulfill-recipient" class="form-label">Nama penerima</label><input name="recipient" id="fulfill-recipient" class="form-control" maxlength="150" value="{{ old('recipient', $ticket->requester?->name) }}"></div>
            <div class="col-12" data-fulfill="delivery"><label for="fulfill-evidence" class="form-label">Bukti penyerahan barang / dana</label><input type="file" name="evidence" id="fulfill-evidence" class="form-control" accept=".jpg,.jpeg,.png,.pdf"><div class="form-text">JPG, PNG, atau PDF; maksimal 10 MB.</div></div>
            <div class="col-12"><label for="fulfill-notes" class="form-label">Catatan / alasan menunggu</label><textarea name="notes" id="fulfill-notes" class="form-control" maxlength="2000" required>{{ old('notes') }}</textarea></div>
            <div class="col-12"><button class="btn btn-primary">Simpan Tindak Lanjut</button></div>
        </form>
    </div>
    @elseif($workflowStatus === 'WAITING_MANAGER_APPROVAL')<div class="alert alert-warning mt-3">Pemenuhan menunggu persetujuan kadep/manager.</div>@endif
@endif
@if(data_get($ticket->payload, 'fulfillments'))
<div class="mt-4"><h3 class="h6">Riwayat pemenuhan</h3>
@foreach(array_reverse(data_get($ticket->payload, 'fulfillments', [])) as $record)
    <div class="border rounded p-3 mb-2 small"><strong>Baris {{ $record['line'] + 1 }} · {{ ['waiting' => 'Menunggu', 'stock' => 'Barang diserahkan', 'funds' => 'Dana diberikan'][$record['method']] }}</strong>
    @if($record['method'] !== 'waiting')<div>Qty: {{ $record['quantity'] }} · Penerima: {{ $record['recipient'] }}</div>@endif
    @if($record['method'] === 'funds')<div>Dana: Rp{{ number_format($record['amount'], 2, ',', '.') }}</div>@endif
    <div style="white-space:pre-wrap">{{ $record['notes'] }}</div><div class="text-muted">{{ $record['recorded_at'] }} · {{ $record['actor_name'] }}</div>
    @if($record['attachment_id'])<a href="{{ route('ticket.attachments.show', [$ticket, $record['attachment_id']]) }}" target="_blank" rel="noopener">Lihat bukti penyerahan</a>@endif</div>
@endforeach
</div>
@endif
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('procurement-fulfillment');
    if (!form) return;
    const method = document.getElementById('fulfill-method');
    const refresh = () => form.querySelectorAll('[data-fulfill]').forEach(group => {
        const active = group.dataset.fulfill === method.value || (group.dataset.fulfill === 'delivery' && method.value !== 'waiting');
        group.hidden = !active;
        group.querySelectorAll('input,select').forEach(input => {input.disabled = !active; input.required = active;});
    });
    method.addEventListener('change', refresh);
    refresh();
});
</script>
