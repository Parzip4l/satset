<tr>
    <td data-number></td>
    <td><input class="form-control" name="items[{{ $index }}][need]" value="{{ $item['need'] ?? '' }}" maxlength="150" aria-label="Kebutuhan detail" required></td>
    <td><textarea class="form-control" name="items[{{ $index }}][description]" maxlength="2000" rows="2" aria-label="Detail deskripsi" required>{{ $item['description'] ?? '' }}</textarea></td>
    <td><input class="form-control" name="items[{{ $index }}][location]" value="{{ $item['location'] ?? '' }}" maxlength="150" aria-label="Lokasi" required></td>
    <td><input type="number" class="form-control" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" max="1000000" step="1" aria-label="Qty" required></td>
    <td><input type="number" class="form-control" name="items[{{ $index }}][estimated_budget]" value="{{ $item['estimated_budget'] ?? '' }}" min="0" max="999999999999.99" step="0.01" aria-label="Perkiraan anggaran per baris" data-budget required></td>
    <td><button type="button" class="btn btn-outline-danger btn-sm" data-remove aria-label="Hapus baris">Hapus</button></td>
</tr>
