# Dokumen UAT dan QA Manual Fitur GA

Versi: 1.0  
Tanggal: 20 Juli 2026  
Aplikasi: SATSET LRTJ  
Modul: General Affairs / BUM

## 1. Tujuan

Dokumen ini digunakan sebagai panduan User Acceptance Test (UAT) oleh user bisnis dan QA manual oleh tester internal untuk memastikan seluruh fitur GA/BUM siap digunakan di lingkungan GA.

## 2. Ruang Lingkup

Fitur yang tercakup:

- Login dan akses role user.
- Dashboard dan menu tiket.
- Ticket umum GA.
- Permintaan konsumsi rapat.
- Permintaan ATK/RTK.
- GA Permintaan dan Temuan berbasis QR/form.
- Review gudang ATK/RTK dan handover barang.
- Dashboard BUM.
- Master barang ATK/RTK.
- Detail barang, mutasi stok, dan kartu stok.
- Penerimaan pengadaan barang.
- Stock opname.
- Laporan bulanan BUM.
- Analytics BUM.
- Master data pendukung ticket.
- Notifikasi.
- Booking ruang meeting.
- VCard/kontak.
- API master, ticket, schema, dan analytics.

## 3. Role Uji

| Role | Fokus Pengujian |
|---|---|
| Requester/Karyawan | Membuat permintaan, melihat tiket sendiri, upload bukti, komentar |
| Atasan/Approver | Approve/reject konsumsi dan ATK/RTK yang perlu approval |
| Admin/BUM | Review tiket GA/BUM, update flow, handover, kelola stok, penerimaan, opname, laporan |
| Admin Sistem | Kelola master data, user, role, status, priority, impact, urgency, schema |

## 4. Data Uji Minimum

- 1 user requester aktif dengan email.
- 1 user approver/manager/admin selain requester.
- 1 user admin/BUM.
- Master status minimal: Open, In Progress, Pending, Resolved, Closed.
- Priority, impact, urgency minimal: Medium.
- Ticket category: BUM, Service Request.
- Problem category: Permintaan Konsumsi Rapat, Permintaan ATK/RTK, GA Permintaan dan Temuan.
- Minimal 3 item consumable aktif: 2 ATK dan 1 RTK, salah satunya low stock.
- Minimal 1 ruang meeting aktif.

## 5. Kriteria Pass/Fail

Pass jika hasil aktual sesuai expected result, data tersimpan, history tercatat, status/workflow benar, stok berubah sesuai transaksi, validasi mencegah input tidak valid, dan tidak ada error 500/UI blocker.

Fail jika data tidak tersimpan, status salah, stok minus tanpa validasi, role bisa mengakses data yang tidak semestinya, file upload tidak sesuai aturan, filter/sort tidak bekerja, atau respon API tidak sesuai format.

## 6. Skenario UAT End-to-End

### UAT-GA-01 Login dan Dashboard

Prasyarat: user aktif tersedia.  
Langkah: login dengan kredensial valid, buka Dashboard, buka menu Ticket dan BUM Dashboard.  
Expected: user masuk aplikasi, dashboard tampil, menu sesuai role, tidak ada halaman error.

### UAT-GA-02 Membuat Ticket Umum GA

Langkah: buka Ticket > General/Create, isi title, description, category, priority/impact/urgency, submit.  
Expected: ticket number terbentuk dengan format TCK-{KODE}-{MMDDYY}-{RUNNING}, status Open, history "Ticket dibuat" tercatat, email notifikasi dicoba dikirim.

### UAT-GA-03 List, Search, Filter Ticket

Langkah: buka Ticket General, gunakan pencarian ticket no/title/requester, filter status, priority, category, department.  
Expected: hasil list sesuai filter; non-admin hanya melihat ticket miliknya; admin dapat melihat seluruh ticket.

### UAT-GA-04 Detail Ticket, Assignment, Status, Komentar

Langkah: buka detail ticket, assign user/departemen, ubah status, tambah komentar.  
Expected: assignment tersimpan, status berubah, history bertambah, komentar tampil dengan user pembuat, email status update dicoba dikirim.

### UAT-GA-05 Permintaan Konsumsi Rapat

Langkah: buka form Permintaan Konsumsi, isi nama kegiatan, tipe kegiatan, tanggal, jam mulai/selesai, lokasi, jumlah peserta, tipe konsumsi, alasan, approver, upload dokumen opsional, submit.  
Expected: ticket dibuat sebagai request_type consumption, title otomatis "Permintaan Konsumsi - {Kegiatan}", workflow_status WAITING_MANAGER_APPROVAL, approval atasan tercatat, lampiran tersimpan jika diupload.

### UAT-GA-06 Approval Konsumsi Oleh Atasan

Langkah: login sebagai approver, buka ticket konsumsi, approve lalu ulangi skenario reject pada data lain.  
Expected: approved mengubah workflow_status ke WAITING_BUM_VERIFICATION; rejected mengubah workflow_status ke REJECTED_BY_MANAGER; requester tidak bisa approve request miliknya sendiri.

### UAT-GA-07 Flow BUM Konsumsi

Langkah: admin/BUM update flow konsumsi ke APPROVED_BY_BUM, ORDERED_TO_VENDOR, RECEIVED, WAITING_ACCOUNTABILITY, REPORTED, CLOSED; isi vendor, tanggal order, estimasi/aktual biaya, tanggal terima, catatan.  
Expected: payload ticket terupdate, history mencatat setiap perubahan, nilai biaya/tanggal tersimpan.

### UAT-GA-08 Upload Bukti Konsumsi

Langkah: upload attendance, documentation, activity report, atau training material di ticket konsumsi.  
Expected: file valid tersimpan dengan tipe lampiran benar; file di luar format/ukuran ditolak.

### UAT-GA-09 Permintaan ATK/RTK Di Bawah Threshold

Langkah: buat permintaan ATK/RTK dengan item, qty, harga satuan sehingga total di bawah threshold approval.  
Expected: ticket request_type atk_rtk dibuat, total_estimated_amount dihitung, workflow_status WAITING_BUM_REVIEW, approver tidak wajib.

### UAT-GA-10 Permintaan ATK/RTK Di Atas Threshold

Langkah: buat permintaan ATK/RTK dengan total sama/lebih besar dari threshold tanpa approver, lalu dengan approver.  
Expected: tanpa approver ditolak validasi; dengan approver berhasil dan workflow_status WAITING_MANAGER_APPROVAL.

### UAT-GA-11 Approval ATK/RTK

Langkah: approver approve/reject ticket ATK/RTK yang perlu approval.  
Expected: approve mengubah workflow_status ke WAITING_BUM_REVIEW; reject ke REJECTED_BY_MANAGER; history approval tercatat.

### UAT-GA-12 Review BUM ATK/RTK

Langkah: admin/BUM buka ticket ATK/RTK, set STOCK_CHECKED, WAITING_PROCUREMENT, READY_TO_HANDOVER, atau CANCELLED; isi approved_qty dan catatan.  
Expected: workflow_status dan approved_qty tersimpan, history review BUM tercatat.

### UAT-GA-13 Handover ATK/RTK dan Pengurangan Stok

Langkah: pada ticket READY_TO_HANDOVER, pilih item, fulfilled_qty, penerima, catatan, submit handover.  
Expected: stok item berkurang sesuai fulfilled_qty, stock movement OUT tercatat reference atk_rtk_request, workflow_status HANDED_OVER, qty fulfilled tidak boleh melebihi approved_qty, stok minus ditolak.

### UAT-GA-14 Gudang ATK/RTK

Langkah: buka halaman Gudang ATK/RTK, cek statistik waiting review/procurement/ready/handover, active tickets, dan low stock.  
Expected: angka statistik sesuai data ticket dan item low stock; pagination dan link detail bekerja.

### UAT-GA-15 GA Permintaan dan Temuan

Langkah: buka form GA Permintaan & Temuan, pilih Permintaan lalu Temuan pada data berbeda, isi lokasi, detail lokasi, deskripsi, expected action, nomor HP, upload evidence.  
Expected: ticket request_type ga_request_finding dibuat, workflow_status WAITING_BUM_REVIEW, title "GA {Permintaan/Temuan} - {Lokasi}", evidence tersimpan jika valid.

### UAT-GA-16 Dashboard BUM

Langkah: buka BUM Dashboard, ubah filter GA type all/Permintaan/Temuan.  
Expected: kartu pending ATK, pending konsumsi, low stock, pending receiving, current opname tampil; breakdown status/lokasi dan recent ticket menyesuaikan filter.

### UAT-GA-17 Master Barang

Langkah: buka BUM Items, tambah item baru, edit item, aktif/nonaktif, filter category, search, sort.  
Expected: code unik divalidasi, data item tersimpan, initial stock membuat stock movement IN, filter/sort/pagination bekerja.

### UAT-GA-18 Detail Barang dan Adjustment Stok

Langkah: buka detail item, lakukan adjustment in dan out, coba out melebihi stok.  
Expected: movement tercatat dengan balance_before/balance_after benar; stok minus ditolak; trend bulanan dan receiving lines tampil.

### UAT-GA-19 Kartu Stok

Langkah: buka Stock Card, filter item, date_from, date_to, sort kolom.  
Expected: mutasi sesuai filter; balance tidak negatif; creator dan reference tampil bila tersedia.

### UAT-GA-20 Penerimaan Pengadaan

Langkah: buat dokumen receiving berisi vendor, PO/DO/GR, tanggal, minimal satu item, qty ordered/received/rejected.  
Expected: reference_number RCV-{YYYYMMDD-HHMMSS} terbentuk, status SUBMITTED, item lines tersimpan.

### UAT-GA-21 Update Receiving dan Penambahan Stok

Langkah: update receiving ke RECEIVED/PARTIALLY_RECEIVED/REJECTED/STORED/CLOSED dengan qty_received dan qty_rejected.  
Expected: delta qty_received menambah stok item, movement IN reference procurement_receiving tercatat, perubahan qty tidak menggandakan stok untuk qty lama.

### UAT-GA-22 Stock Opname

Langkah: buat opname period YYYY-MM, pilih item, isi physical_stock, catatan.  
Expected: opname status CLOSED, system_stock tersimpan, variance dihitung, stok disesuaikan via movement ADJUSTMENT jika variance tidak nol.

### UAT-GA-23 Laporan Bulanan BUM

Langkah: buka Reports, pilih periode bulan.  
Expected: usage OUT, receivings bulan tersebut, dan ticket konsumsi bulan tersebut tampil sesuai periode.

### UAT-GA-24 Analytics BUM

Langkah: buka Analytics, pilih filter item/departemen/periode bila tersedia, pastikan chart/data summary, usage trend, forecast, request trend, meeting consumption, receiving trend, recommendation dimuat.  
Expected: setiap endpoint mengembalikan JSON success true dan data ter-render tanpa error.

### UAT-GA-25 Master Data Ticket

Langkah: kelola division, lokasi, PIC, department, department-problem-assign, problem category, priority, status, impact, urgency, ticket form schema.  
Expected: create/edit/delete/list bekerja sesuai fitur yang tersedia; schema category dapat diambil melalui route form schema.

### UAT-GA-26 Notifikasi

Langkah: buka notifikasi, mark as read satu item, mark all, clear all.  
Expected: status read berubah, counter/list refresh, clear menghapus notifikasi user sesuai aturan.

### UAT-GA-27 Booking Ruang Meeting

Langkah: kelola meeting room, buat booking di calendar, cek events, hapus booking.  
Expected: booking tampil pada kalender, event API mengembalikan data, delete menghilangkan booking.

### UAT-GA-28 VCard/Kontak

Langkah: buka /contact/{id}, download vcard.  
Expected: profil kontak publik tampil dan file vCard dapat diunduh.

### UAT-GA-29 API Master dan Ticket

Langkah: panggil API /api/v1/master/*, /api/v1/tickets, /api/v1/tickets/{id}, /api/v1/tickets/{id}/history, POST/PUT ticket, /api/v1/ticket-form-schema/{category}.  
Expected: response sukses sesuai kontrak JSON, validasi input berjalan, detail/history ticket sesuai data.

### UAT-GA-30 API Analytics

Langkah: panggil /api/bum/analytics/* dan /api/v1/bum/analytics/* untuk semua endpoint analytics.  
Expected: response JSON berisi success true dan data; filter invalid tidak menyebabkan error 500.

## 7. Checklist QA Manual Per Build

- Login valid/invalid diuji.
- Role requester tidak dapat melihat ticket user lain.
- Admin/BUM dapat melakukan aksi review dan inventory.
- Semua form wajib menolak field kosong yang required.
- Upload menerima format yang diizinkan dan menolak format/ukuran tidak valid.
- Status/workflow ticket berubah sesuai alur.
- History tercatat untuk create, status update, assign, approval, review, handover, flow konsumsi.
- Email failure tidak membuat proses utama gagal.
- Stok tidak pernah menjadi negatif.
- Stock card balance_before/balance_after sesuai transaksi.
- Search, filter, sort, pagination bekerja.
- Halaman responsive minimal desktop dan mobile.
- API mengembalikan JSON valid dan tidak membocorkan stack trace.

## 8. Template Log Defect

| Defect ID | Modul | Skenario | Severity | Step Reproduce | Expected | Actual | Evidence | Status | Owner |
|---|---|---|---|---|---|---|---|---|---|
| DF-001 |  |  | Critical/High/Medium/Low |  |  |  | Screenshot/log | Open/In Progress/Fixed/Retest/Closed |  |

## 9. Sign-Off UAT

| Nama | Role | Keputusan | Catatan | Tanggal | Tanda Tangan |
|---|---|---|---|---|---|
|  | User Bisnis/GA | Accept / Accept with Notes / Reject |  |  |  |
|  | QA | Pass / Pass with Defects / Fail |  |  |  |
|  | IT/Developer | Release / Hold |  |  |  |

