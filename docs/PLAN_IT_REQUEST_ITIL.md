# Rencana Pembangunan Layanan IT Satset dan Konfigurasi Dinamis Superadmin

Tanggal: 28 Oktober 2026
Status: Acuan pengembangan bertahap; kebijakan operasional dan gerbang rilis tetap memerlukan penetapan pemilik layanan.
Dasar pemeriksaan: source code, migration, model, controller, route, seeder, dan Blade pada repository Satset. Audit database Docker/MySQL serta verifikasi penerapan telah dilakukan; kebijakan layanan baru tetap mengikuti gerbang publikasi tiap fase.

Progres 28 Oktober 2026: **Fase 0–4 selesai secara teknis dan diterapkan pada MySQL Docker local**. Fase 4 menyediakan kalender/kebijakan SLA berversi, clock respons/pemulihan/pemenuhan, pause/resume, prioritas/transfer/reopen yang diaudit, breach/eskalasi, dashboard operasional, major incident, outbox dan runtime scheduler/worker IT tersendiri. Suite 176 test dan 45 pemeriksaan MySQL lulus; worker sungguhan serta rekonsiliasi dua proses terverifikasi. Kedua container local sehat dengan heartbeat alami, tanpa backlog/kegagalan. Source tetap 15 request, 3 draft tanpa versi aktif dan pengajuan tertutup; target nyata/tim belum diaktifkan otomatis. ID 1/31 tetap hanya mendapat capability konfigurasi/publikasi. Bukti dan batas tersedia di [IT_REQUEST_PHASE_0_1.md](IT_REQUEST_PHASE_0_1.md), [IT_REQUEST_PHASE_2.md](IT_REQUEST_PHASE_2.md), [IT_REQUEST_PHASE_3.md](IT_REQUEST_PHASE_3.md), dan [IT_REQUEST_PHASE_4.md](IT_REQUEST_PHASE_4.md). Fase 5–6 belum diimplementasikan.

## 1. Tujuan dan batas pekerjaan

Membangun ulang bagian Request Umum menjadi Layanan IT yang memiliki katalog, form, antrean tim, workflow, persetujuan, SLA, dan pelaporan tersendiri. Satset memiliki empat layanan utama:

| Kode stabil | Nama tampilan awal | Proses utama |
|---|---|---|
| `atk_rtk` | ATK/RTK | Permintaan barang, review, pengadaan, serah terima |
| `consumption` | Konsumsi | Pengajuan, persetujuan, pemenuhan, pertanggungjawaban |
| `ga_request_finding` | Permintaan & Temuan | Pelaporan, tindak lanjut GA, bukti penyelesaian |
| `it` | Layanan IT | Incident, Service Request, dan proses operasional IT terkait |

Nama, deskripsi, ikon dari daftar aman, urutan tampilan, dan status penerimaan pengajuan baru dapat diatur superadmin. Kode stabil dan hubungan data tidak dapat diganti dari form pengaturan. Menonaktifkan layanan tidak menghilangkan tiket lama.

Batas rewrite:

- Alur bisnis ATK/RTK, Konsumsi, dan Permintaan & Temuan dipertahankan.
- Fondasi bersama yang layak digunakan kembali: identitas pengguna, tiket, lampiran, komentar, riwayat, notifikasi, dan koneksi portal.
- IT memiliki konfigurasi dan kewenangan operasional terpisah dari GA.
- Tiket Saya tetap menampilkan seluruh tiket yang berhak dilihat pengguna, dengan filter layanan.
- Problem dan Change merupakan catatan operasional IT yang dapat dihubungkan ke tiket; keduanya bukan layanan utama kelima atau keenam.
- Laporan ini menggunakan praktik operasional ITIL 4 sebagai acuan eksplisit. Rancangan tidak menyatakan sertifikasi atau kepatuhan menyeluruh terhadap seluruh ITIL.

## 2. Kondisi existing dan keputusan pemanfaatan

| Komponen existing | Bukti di repository | Keputusan |
|---|---|---|
| Tiket utama di tabel `requests` | `app/Models/Master/Ticket.php` | Pertahankan identitas dan referensi tiket; tambahkan pemisahan layanan secara bertahap |
| Jenis tiket Incident, Service Request, Change Request, BUM | `database/seeders/TicketCategorySeeder.php` | Pisahkan dimensi layanan utama dari jenis catatan IT; jangan menganggap BUM sebagai jenis IT |
| Kategori bertingkat `parent_id` | `app/Models/Master/ProblemCategory.php` | Gunakan kembali dengan cakupan layanan dan validasi hierarki |
| Pemetaan kategori ke departemen | `app/Http/Controllers/Master/DepartmentCategoryController.php` | Migrasikan menjadi aturan penerima yang tegas; departemen organisasi dibedakan dari tim teknis |
| Routing mengambil departemen pertama hasil pemetaan | `app/Services/MobileSatsetTicketService.php`, `TicketController.php` | Ganti untuk IT dengan aturan terurut dan antrean fallback yang eksplisit |
| Form JSON berdasarkan `ticket_category_id` | `TicketFormSchemaController.php`, `ticket/create.blade.php` | Kembangkan menjadi template berversi per katalog/jenis; validasi pada server |
| Master priority, impact, urgency, status | Model master dan `TicketMasterSeeder.php` | Manfaatkan dengan matriks, makna status, dan aturan transisi IT |
| Assignment user/departemen di tiket | `TicketController::assign()` | Gunakan dasar penugasan; tambah keanggotaan tim, penerimaan tugas, transfer, dan audit |
| Update status dan assign umum web dibatasi admin | `TicketController::updateStatus()`, `assign()` | Bangun policy khusus per aksi dan layanan |
| Approval, audit keputusan, resolver portal | `SatsetInitialApprovalService.php`, `SatsetApprovalDecisionService.php` | Gunakan komponen bersama; pisahkan aturan approval IT dari kondisi GA |
| Komentar, lampiran, riwayat, notifikasi | Model terkait, `TicketObserver.php` | Tambah visibilitas komentar, klasifikasi data, serta event IT |
| API mobile dan intranet approval | `routes/api.php` | Perluas secara kompatibel dan samakan validasi/policy web-mobile |
| SLA, routing rules, escalation rules | Migration awal, model dan seeder terkait | Struktur awal saja; belum ditemukan mesin runtime yang menghitung dan mengeksekusi aturan |
| Assignment lifecycle, watchers, status logs | Migration awal | Audit pemakaian dan relasi sebelum digunakan; jangan menganggap tabel berarti fitur aktif |
| Stok/inventori GA | Modul BUM | Tidak dianggap sebagai inventori aset IT atau CMDB |

Catatan teknis yang harus diselesaikan sebelum reuse:

1. Nama kolom model/seeder SLA, routing, dan eskalasi berbeda pada sebagian bagian dari migration. `Sla` belum menetapkan nama tabel singular `sla` secara eksplisit.
2. Relasi assignment perlu dirapikan: migration memakai `request_id`, sedangkan beberapa relasi mengandalkan nama foreign key berbeda; ada trailing space pada `assignee_user_id ` di fillable.
3. Saat ini status umum dan `payload.workflow_status` berdampingan. Untuk IT, tetapkan satu status operasional sebagai sumber kebenaran dengan pemetaan eksplisit ke status ringkas bersama.
4. Seeder kategori IT bukan bukti data tersebut terpasang di database. Seeder utama yang diperiksa tidak memanggil seluruh seeder fondasi lama.
5. Peran superadmin khusus belum ditetapkan dalam rancangan akses existing yang diperiksa; harus dibuat sebagai capability eksplisit, bukan sekadar mengganti label admin.

## 3. Model klasifikasi IT yang dituju

Dimensi disimpan terpisah agar kategori teknis tidak merangkap sebagai layanan, jenis tiket, atau tim.

| Dimensi | Makna | Contoh |
|---|---|---|
| Layanan utama Satset | Pemisahan modul/operasional | IT |
| Jenis catatan | Tujuan proses | Incident atau Service Request |
| Layanan IT | Layanan yang digunakan/terdampak | Email perusahaan, ERP, internet kantor |
| Item katalog permintaan | Permintaan terdefinisi dengan form dan proses | Pembuatan akun email, akses ERP |
| Kategori/subkategori | Bidang teknis dan rincian kebutuhan | Jaringan / Wi-Fi |
| Lokasi | Lokasi kejadian atau pemenuhan | Kantor / lantai / ruangan |
| Aset/CI | Perangkat atau komponen layanan | Laptop atau access point tertentu |
| Support team | Kelompok pelaksana teknis | Service Desk, Network, Application Support |
| PIC | Orang yang bertanggung jawab atas penanganan | Anggota tim penerima |
| Service owner | Pemilik akuntabilitas layanan | Pemilik layanan ERP |

Contoh: `IT / Incident / Internet kantor / Jaringan / Wi-Fi / Kantor lantai 2 / AP-02 / Network / PIC A`.

Pengguna memilih bahasa sederhana seperti “Laporkan Gangguan” atau “Ajukan Layanan”. Kategori teknis dapat disarankan dari katalog; Service Desk dapat mengoreksi dengan alasan tanpa menghapus waktu pelaporan awal.

## 4. Tingkat dinamika dan batas superadmin

| Tingkat | Makna | Contoh |
|---|---|---|
| D1 — Tampilan | Mengubah label dan presentasi | Label field, bantuan pengisian, urutan |
| D2 — Master | Mengelola pilihan dan referensi | Kategori, lokasi, tim, katalog |
| D3 — Aturan | Mengubah kebijakan melalui builder terstruktur | Required bersyarat, routing, approval, SLA |
| S — Sistem | Nilai/struktur dikendalikan aplikasi | ID tiket, timestamp audit, evaluasi otorisasi |

Superadmin dapat mengatur D1–D3 sesuai capability. Mesin aplikasi tetap mengendalikan validasi, akses, identitas, histori, dan integritas transaksi. Pengaturan memakai operator yang disediakan aplikasi; tidak menerima PHP, JavaScript, SQL, atau ekspresi kode bebas.

## 5. Pemetaan field tiket dan formulir

### 5.1 Identitas dan klasifikasi

| Field/key usulan | Sumber/pengisi | Yang dapat diatur superadmin | Batas wajib |
|---|---|---|---|
| `id`, `ticket_no` | Sistem | Pola nomor dalam format yang didukung, prefix per layanan, label | Unik; nomor lama tidak berubah; generator aman terhadap submit bersamaan |
| `service_domain_id` | Pintu masuk layanan | Nama/urutan/deskripsi/aktif layanan | IT selalu memiliki cakupan akses dan referensi stabil |
| `record_type` | Pengguna atau template | Nama tampilan dan jenis yang tersedia pada katalog | Semantik Incident/Service Request tetap; tidak dapat membuat jenis proses arbitrer |
| `it_service_id` | Pengguna/Service Desk | Daftar layanan, owner, deskripsi, cakupan, tim fallback | Referensi lama tetap tersimpan jika layanan dinonaktifkan |
| `catalog_item_id` | Pengguna untuk Service Request | Item, penerima yang memenuhi syarat, template dan versi proses | Item aktif dan sesuai jenis/layanan; pilihan divalidasi server |
| `category_id`, `subcategory_id` | Pengguna/Service Desk | Hierarki, label, deskripsi, aktif, default | Tidak boleh siklus; kategori harus dalam layanan IT; subkategori harus anak yang sah |
| `location_id` | Pengguna/master | Hierarki lokasi, label, wajib berdasarkan kebutuhan | Referensi harus valid; perubahan lokasi dicatat |
| `asset_id` / `ci_id` | Pemohon/teknisi | Jenis aset yang dapat dipilih, wajib per template, label | Pemohon hanya melihat aset yang berhak diakses; CI teknis dapat dibatasi untuk petugas |
| `source_channel` | Sistem | Label kanal dan kanal pengajuan yang diaktifkan | Kanal asli tercatat otomatis, tidak dipercaya dari payload pengguna |

### 5.2 Identitas pemohon dan detail kebutuhan

| Field/key usulan | Sumber/pengisi | Pengaturan superadmin | Batas wajib |
|---|---|---|---|
| `requester_id` | Akun login | Label tampil | Tidak dapat dipalsukan melalui form |
| `requested_for_id` | Pemohon/petugas berwenang | Pengajuan atas nama orang lain, cakupan penerima | Kewenangan diverifikasi; aktor dan penerima dibedakan |
| Unit, nama, email pemohon | Identitas/organisasi portal | Tampil, read-only, pemetaan atribut resmi | Tidak mengubah master organisasi melalui tiket; simpan snapshot bila diperlukan |
| Kontak alternatif | Pemohon | Tipe, wajib, format, bantuan | Tidak digunakan mengganti identitas atau otorisasi akun |
| `title` | Pemohon/template | Label, batas panjang, pola judul otomatis | Judul tetap tersedia; batas platform tidak dapat dilampaui |
| `description` | Pemohon | Label, petunjuk, minimum panjang, wajib | Konten di-escape; tidak mengeksekusi HTML/script |
| Waktu mulai kejadian | Pemohon untuk Incident | Wajib, tampil, toleransi tanggal | Pisahkan dari waktu tiket dibuat oleh server |
| Cakupan pengguna terdampak | Pemohon/Service Desk | Pilihan, bantuan, wajib | Menjadi masukan penilaian dampak; bukan hak mengubah prioritas final |
| Alasan bisnis | Pemohon untuk request tertentu | Wajib bersyarat, panjang, bantuan | Tidak menggantikan keputusan approval |
| Tanggal dibutuhkan | Pemohon | Rentang, lead time, wajib | Tidak otomatis menjadi janji SLA; pengecualian lead time perlu alasan |
| Lampiran | Pemohon/teknisi | Wajib per tahap, jumlah, tipe file, ukuran dalam batas platform | Pemeriksaan MIME/ukuran server dan otorisasi unduh; tidak boleh menerima jenis executable |
| Persetujuan informasi/penerimaan | Pemohon | Teks dan kapan ditampilkan | Catat versi teks dan waktu keputusan; bukan persetujuan approver |

### 5.3 Field tambahan per kategori/katalog

| Kategori awal | Contoh field dinamis | Contoh aturan |
|---|---|---|
| Hardware & perangkat | Jenis perangkat, asset tag, gejala, kondisi fisik, lokasi perangkat | Asset tag wajib jika perangkat terdaftar; kerusakan fisik meminta foto bila memungkinkan |
| Jaringan & koneksi | Jenis koneksi, lokasi, SSID, cakupan gangguan, mulai kejadian | SSID tampil untuk Wi-Fi; pengguna tidak wajib mengetahui alamat IP |
| Aplikasi & sistem | Nama aplikasi, modul, aktivitas, pesan error, frekuensi | Pesan error/detail langkah tampil untuk gangguan aplikasi |
| Akun & akses | Aplikasi tujuan, penerima akses, role akses, alasan bisnis, masa berlaku | Role akses berasal dari katalog yang disetujui; approval sesuai pemilik akses |
| Software & lisensi | Produk, versi, perangkat tujuan, kebutuhan lisensi, alasan | Software hanya dari daftar yang diizinkan atau jalur review pengecualian |
| Server & infrastruktur | Lingkungan, layanan/CI, gejala, kebutuhan kapasitas | Detail infrastruktur dibatasi kepada petugas yang berwenang |
| Keamanan informasi | Jenis indikasi, waktu diketahui, aset terkait, bukti | Tiket dibatasi ke tim keamanan; form tidak meminta password, token, atau private key |

Kategori adalah baseline usulan dan dapat ditambah/nonaktif oleh superadmin. Satu kategori boleh melayani Incident dan Service Request dengan template berbeda.

### 5.4 Kemampuan form builder

Superadmin dapat mengatur:

- Tipe: text, textarea, integer/decimal, date, datetime, time, select, multiselect, radio, checkbox, file, dan referensi master yang diizinkan.
- Kode field stabil, label, bantuan, placeholder, section, urutan, lebar tampilan, serta bahasa tampilan bila didukung.
- Required, nilai default, min/max, panjang teks, daftar pilihan berkode stabil, serta validasi format bawaan.
- Kondisi tampil/wajib/read-only menggunakan `equals`, `not_equals`, `in`, `is_empty`, serta kombinasi AND/OR yang dibatasi.
- Visibilitas menurut peran dan tahap; field internal tidak dikirim ke API pengguna tanpa hak.
- Sumber opsi dari master yang telah diizinkan; tidak menyediakan URL/query bebas.
- Field yang dipakai sebagai filter/laporan melalui registry dan indeks terkontrol.

Ketentuan mesin form:

1. Validasi dijalankan di server dengan schema versi yang sama pada web dan mobile.
2. Field tersembunyi tidak boleh menjadi cara mengirim nilai terlarang. Field yang tidak diizinkan ditolak; default hanya diterapkan server sesuai aturan.
3. Ketergantungan field harus bebas siklus. Referensi pada field/opsi yang dihapus harus terdeteksi sebelum publish.
4. Key dan tipe field yang telah memiliki jawaban tidak diubah secara destruktif. Perubahan makna/tipe membuat versi baru atau field baru.
5. Data klasifikasi utama tetap berada pada kolom/relasi terstruktur. Field tambahan disimpan sebagai jawaban berversi; payload tidak boleh dipakai menimpa atribut sistem.
6. File disimpan melalui mekanisme attachment, bukan sebagai data base64 bebas dalam jawaban form.

## 6. Field operasional dan aturan yang dapat dikonfigurasi

| Area/field | Pengaturan superadmin | Pemilik nilai saat operasional | Batas sistem |
|---|---|---|---|
| Impact | Pilihan dan definisi cakupan dampak | Service Desk/koordinator memvalidasi masukan pemohon | Perubahan dicatat |
| Urgency | Pilihan dan definisi kebutuhan waktu | Service Desk/koordinator | Tidak hanya mengikuti kata “urgent” dari pemohon |
| Priority | Matriks impact × urgency, label, urutan | Dihitung sistem; override oleh petugas berwenang | Alasan override wajib; tidak boleh menghapus histori |
| Support team | Nama tim, anggota, kategori/layanan yang ditangani, koordinator | Koordinator/pengelola tim sesuai hak | Keanggotaan bukan otomatis hak akses semua tiket IT |
| PIC | Metode penugasan manual, self-claim, atau otomatis yang didukung | Koordinator/teknisi sesuai metode | Harus anggota aktif dan memiliki hak pada tiket; satu penanggung jawab utama |
| Routing | Kondisi, urutan, tim tujuan, fallback | Sistem dan Service Desk untuk koreksi | First-match dengan prioritas eksplisit; aturan tumpang tindih diberi peringatan; tidak boleh tanpa fallback |
| Status | Label, urutan, kelas status, alasan wajib | Aktor sesuai transisi | Kelas semantik dan event timestamp dilindungi |
| Transisi | Asal/tujuan, aktor, prasyarat, field wajib, event | Aktor berwenang | Tidak dapat melewati approval/kontrol akses; workflow harus memiliki jalur penyelesaian |
| Pending reason | Menunggu pengguna/vendor/barang/perubahan terjadwal | Teknisi/koordinator | Tidak otomatis menghentikan SLA; mengikuti kebijakan per clock |
| Approval | Kondisi pemicu, level, mode urut/paralel yang didukung, resolver, pengganti, tenggat | Approver yang ditetapkan | Tidak self-approve; resolver gagal masuk exception queue, tidak auto-approve |
| Task pemenuhan | Template tugas, urutan/dependensi, tim, bukti wajib | PIC tugas | Penyelesaian tiket mengikuti tugas wajib; dependency tidak boleh siklus |
| Resolution | Kode solusi, field/bukti wajib, template catatan | Teknisi | Tidak dapat resolve tanpa alasan/hasil sesuai jenis tiket |
| Penutupan/reopen | Masa konfirmasi, reminder, auto-close, periode reopen | Pemohon/Service Desk/sistem sesuai hak | Penutupan otomatis harus diinformasikan; histori siklus dan clock tetap tersedia |
| Notifikasi | Event, penerima berbasis peran, template, kanal, reminder | Sistem | Placeholder terdaftar; catatan internal/data terbatas tidak bocor ke penerima umum |
| Komentar | Mode publik/internal, hak kirim dan baca | Pemohon/petugas | Komentar internal tidak diserialisasi ke pengguna umum |
| Kerahasiaan | Kelas tiket dan kelompok yang berhak | Petugas berwenang | Pemisahan GA/IT dan pembatasan lampiran ditegakkan pada server |
| CSAT | Pertanyaan, skala, waktu undangan, periode respons | Pengguna | Satu respons per siklus sesuai kebijakan, tidak dapat diedit teknisi |
| Dashboard | Filter, widget, ambang peringatan, akses laporan | Pengguna sesuai peran | Ekspor dan agregat tetap mengikuti scope akses |

### 6.1 Konfigurasi SLA, OLA, dan vendor

| Pengaturan | Isi |
|---|---|
| Pemilihan kebijakan | Layanan IT + jenis tiket + item katalog/kategori + prioritas; urutan kecocokan dan fallback eksplisit |
| Target | Respons manusia pertama, pemulihan incident, pemenuhan request; target penyelesaian tambahan jika dibutuhkan |
| Kalender | Zona waktu Asia/Jakarta, hari kerja, jam kerja, libur, atau layanan 24×7 |
| Mulai/berhenti | Definisi event tiap clock; notifikasi otomatis bukan respons manusia |
| Menunggu | Alasan yang boleh menjeda clock tertentu sesuai kesepakatan layanan; menunggu internal/vendor tidak dianggap pause secara default |
| Eskalasi | Ambang sebelum deadline dan setelah pelanggaran, penerima, kanal, serta frekuensi pengingat |
| Target internal/vendor | OLA per tim dan komitmen vendor yang mendukung target pengguna; penugasan vendor tetap memiliki PIC internal |
| Perubahan prioritas/transfer | Rehitung dengan kebijakan eksplisit dari waktu awal; simpan deadline lama dan alasan, jangan menyembunyikan breach |
| Reopen | Siklus baru ditautkan ke siklus sebelumnya; total durasi dan pencapaian awal tetap dapat dilaporkan |

Angka SLA belum ditetapkan dalam dokumen ini. Target harus disepakati service owner berdasarkan kritikalitas dan kapasitas dukungan. Perpindahan tim tidak mereset waktu pelaporan atau menghapus pelanggaran sebelumnya.

## 7. Field yang tidak boleh bebas diubah superadmin

- Primary key, identitas aktor, waktu dibuat server, riwayat perubahan, rekam keputusan approval, dan sumber kanal asli.
- Hasil pemeriksaan policy, signature portal, token SSO, secret integrasi, dan metadata keamanan. Credential dikelola melalui konfigurasi server/secret store yang terpisah dari form builder.
- Makna inti kelas status: aktif, menunggu, selesai secara teknis, ditutup, ditolak, dan dibatalkan.
- Semantik jenis Incident, Service Request, Problem, dan Change.
- Snapshot versi konfigurasi yang telah dipakai tiket dan nilai SLA/audit historis.
- Hak untuk memalsukan identitas, mengubah keputusan approver, menghapus catatan internal dari audit, atau menerobos pembatasan layanan.

Superadmin mengelola konfigurasi; hak melihat tiket sensitif, menyetujui permintaan, atau mengerjakan tiket diberikan sebagai capability terpisah. Tidak ada hak baca isi tiket keamanan otomatis hanya karena memiliki akses konfigurasi.

## 8. Siklus konfigurasi superadmin

Alur konfigurasi: `Draft → Validasi → Preview/Simulasi → Publish → Retired`.

1. Buat draft dari versi aktif dan pilih cakupan IT, jenis tiket, katalog/kategori yang dituju.
2. Validasi referensi, loop kondisi, konflik routing, resolver approver, transisi buntu, kalender, dan hak akses.
3. Preview sebagai pemohon, teknisi, dan approver; simulasikan data contoh untuk melihat form, antrean, approval, prioritas, serta deadline.
4. Publish secara atomik sebagai satu bundle kompatibel: form, workflow, routing, approval, dan SLA.
5. Tiket baru mengikat versi bundle; tiket berjalan tetap memakai versi awal. Untuk perubahan keanggotaan/hak akses berlaku policy keamanan terkini, bukan hak lama yang dibekukan.
6. Perubahan konfigurasi pada tiket berjalan memerlukan aksi migrasi eksplisit, validasi status, alasan, serta audit; tidak melalui publish biasa.
7. Rollback mengaktifkan versi sebelumnya untuk pengajuan baru. Tiket yang telah tercipta tidak otomatis ditulis ulang.
8. Master yang direferensikan dinonaktifkan/diarsipkan; penghapusan hanya untuk draft yang belum dipakai.

Audit konfigurasi merekam aktor, waktu, alasan, sebelum/sesudah, versi, dan hasil publikasi. Aksi konfigurasi membutuhkan capability `manage_it_configuration`; publish memakai capability terpisah `publish_it_configuration`. Pemisahan orang penyusun/penerbit dapat diberlakukan oleh kebijakan organisasi.

## 9. Workflow berbasis praktik ITIL

Status berikut adalah usulan implementasi Satset, bukan daftar status yang diwajibkan ITIL.

### 9.1 Incident — pemulihan layanan

`Submitted → Triage → Assigned → In Progress → Resolved → Closed`.

- Triage mengidentifikasi layanan terdampak, kategori, dampak, urgensi, duplikasi, serta kebutuhan major incident/security handling.
- Incident tidak melewati approval administratif permintaan sebelum triage dan pemulihan. Tindakan perubahan tetap memerlukan otorisasi sesuai model Change, termasuk jalur emergency.
- Status Waiting dapat dipakai dengan alasan, PIC, tindak lanjut berikutnya, dan aturan clock yang jelas.
- Resolved berarti layanan telah pulih; workaround yang sah dapat menjadi hasil pemulihan. RCA dapat dilanjutkan pada Problem terpisah.
- Catat gejala, langkah diagnosis, tindakan, hasil, waktu pulih, dan kode solusi. Pemohon mengonfirmasi atau reopen sesuai kebijakan.
- Tiket salah kategori dapat dipindahkan. Duplikat ditautkan ke incident utama dengan alasan dan pemetaan komunikasi, tanpa menghapus laporan asli.
- Major incident memakai koordinator, pencatatan kronologi, interval pembaruan komunikasi, linked incidents, dan review pascapemulihan.

### 9.2 Service Request — pemenuhan terdefinisi

`Submitted → Validation → Pending Approval (jika perlu) → Queued → In Progress → Fulfilled → Closed`.

- Setiap item katalog menetapkan penerima yang berhak, informasi wajib, model persetujuan, tugas pemenuhan, target, dan bukti selesai.
- Tidak semua request perlu approval. Pre-authorized request hanya berlaku pada item dan kondisi yang telah ditetapkan.
- Approver ditentukan lewat resolver portal atau service owner yang sah. Kegagalan resolver memerlukan penanganan; tidak memakai user ID contoh sebagai fallback.
- Approver dapat approve/reject/request revision sesuai model. Revisi material setelah approval memicu persetujuan ulang berdasarkan field pemicu yang dikonfigurasi.
- Request akses mencatat penerima, hak yang disetujui, masa berlaku, pelaksana, dan bukti verifikasi; hak implementasi tidak boleh lebih luas dari yang disetujui.
- Pengadaan/vendor dapat menjadi tugas terkait; PIC internal tetap bertanggung jawab atas komunikasi dan progres.
- Cancel/Rejected adalah hasil terminal terpisah dari Fulfilled; wajib alasan dan otorisasi.

### 9.3 Problem — penyebab dan pencegahan berulang

`Identified → Assessment → Investigation → Known Error/Workaround (bila tersedia) → Remediation → Review → Closed`.

Catatan Problem memiliki owner, incident terkait, layanan/CI, dampak, analisis penyebab, workaround, known error, tindakan pencegahan, dan relasi Change. Status known error tidak berarti incident belum boleh dipulihkan. Satu Problem dapat memiliki banyak Incident dan Change.

### 9.4 Change — pengendalian perubahan

`Draft → Assessment → Authorized → Scheduled → Implementing → Review → Closed`.

- Field: jenis standard/normal/emergency, layanan/CI terdampak, alasan, risiko, dampak, rencana implementasi, pengujian, rollback, jadwal, authority, serta hasil aktual.
- Standard change memakai model yang telah diotorisasi; normal change memakai penilaian dan authority sesuai risiko; emergency memiliki jalur otorisasi cepat dan review lanjutan.
- Tidak semua Change wajib rapat CAB. Authority disesuaikan risiko dan kebijakan organisasi.
- Service Request dan Change dapat saling terkait, tetapi persetujuan request tidak otomatis menjadi otorisasi perubahan produksi.
- Pengajuan boleh ditolak, diminta revisi, dibatalkan, atau dinyatakan gagal/di-rollback dengan rekam hasil yang jelas.

## 10. Akses dan struktur menu

| Peran/capability | Cakupan |
|---|---|
| Pemohon | Membuat dan melihat tiket sendiri/atas nama sesuai hak, komentar publik, konfirmasi, reopen |
| Service Desk | Triage, koreksi klasifikasi, routing, komunikasi, penggabungan relasi sesuai cakupan |
| Teknisi | Tiket tim/PIC yang diizinkan, diagnosis, tugas, catatan internal, usulan penyelesaian |
| Koordinator IT | Distribusi pekerjaan, eskalasi, override prioritas beralasan, major incident sesuai scope |
| Approver | Detail yang diperlukan untuk persetujuan dan keputusan pada request yang ditujukan kepadanya |
| Service owner | Katalog dan target layanan yang dimiliki, evaluasi kualitas, persetujuan sesuai mandat |
| Problem/Change owner dan change authority | Aksi sesuai proses serta cakupan yang diberikan |
| Superadmin konfigurasi | Master, builder, simulasi, versi; publish hanya jika memiliki capability penerbitan |
| Auditor | Akses baca audit/laporan sesuai mandat; tidak mengubah tiket |

Menu pemohon: empat kartu layanan, Tiket Saya, Persetujuan Saya, Panduan IT.
Menu operasional IT: Ringkasan IT, Antrean Tiket, Penugasan Saya, Major Incident, Problem, Change, Knowledge, Aset/CI, SLA & Laporan. Menu lanjutan muncul mengikuti fase implementasi dan hak akses.
Menu superadmin: Katalog, Kategori, Form Builder, Workflow, Routing, Approval, Tim & Hak Akses, Prioritas, SLA/Kalender/Eskalasi, Notifikasi, Versi & Audit.

## 11. Model data usulan

Nama tabel berikut merupakan usulan logis. Nama final ditentukan setelah audit database aktif agar tidak bertabrakan dengan tabel existing.

| Entitas | Tujuan dan relasi |
|---|---|
| `service_domains` | Empat layanan Satset, dengan kode stabil |
| `requests` + `it_ticket_details` | Tiket bersama dan atribut IT: jenis, layanan, katalog, kategori, tim/PIC, status IT, lokasi, versi konfigurasi |
| `it_services`, `it_catalog_items` | Service owner, katalog pemenuhan, eligibility, template default |
| Kategori existing dengan scope layanan | Hierarki kategori IT; kategori GA lama tetap memiliki mapping |
| `support_teams`, `support_team_members` | Tim teknis di luar struktur departemen organisasi; keanggotaan dan peran |
| `it_configuration_versions` | Bundle berversi beserta status publikasi, audit dan checksum |
| `it_form_templates`, `it_form_versions`, jawaban | Definisi dan jawaban yang terikat versi |
| Definisi workflow, transisi, aturan | Status semantik, aksi, prasyarat, actor scope |
| Routing dan approval policies | Kondisi terstruktur, prioritas evaluasi, resolver dan fallback |
| Assignment events dan fulfillment tasks | Penugasan/transfer, penerimaan, dependensi, hasil pekerjaan |
| SLA policies, calendars, clocks, events | Snapshot target, deadline, pause, resume, breach dan eskalasi yang dapat diaudit |
| Komentar/lampiran existing + visibility | Publik/internal/restricted dengan policy di semua kanal |
| `it_ticket_links` | Duplikat, parent/child, terkait Problem/Change/major incident; tipe link eksplisit |
| `it_problems`, `it_changes` | Catatan terpisah dan hubungan ke tiket, layanan, CI |
| `it_assets`, `it_configuration_items`, relasi | Kepemilikan aset, komponen layanan, dan ketergantungan; mulai dari register minimum |
| Knowledge, feedback, improvement records | Artikel disetujui, CSAT, tindakan perbaikan dan owner |

Prinsip integrasi: gunakan satu lapisan domain IT untuk web dan API, transaksi untuk perubahan status/approval/claim, outbox atau mekanisme event andal untuk pekerjaan async, serta idempotency untuk submit dan notifikasi/eskalasi. Worker SLA memproses deadline secara berkala dengan locking agar tidak mengirim eskalasi ganda.

## 12. Rencana pembangunan dan kriteria selesai

### Fase 0 — Audit dan baseline migrasi

Pekerjaan:

- Inventaris schema/data aktif, route konsumen web/mobile, integrasi portal, role, scheduler, dan backup.
- Audit ketidaksesuaian model/migration/seeder, relasi assignment, dan otorisasi endpoint yang akan digunakan ulang.
- Hitung tiket per `request_type`, jenis, kategori, status, dan penerima. Tandai Request Umum yang ambigu untuk peninjauan.
- Sepakati service owner, katalog awal, tim, capability, workflow, target layanan, dan batas akses keamanan.

Hasil: matriks existing → target, daftar keputusan bisnis, sampel data uji yang disanitasi, strategi backup/restore dan migrasi.
Selesai jika setiap kelompok tiket lama mempunyai mapping atau antrean pengecualian yang jelas; tidak ada asumsi bahwa semua `general` adalah IT.

### Fase 1 — Pemisahan empat layanan dan policy IT

Pekerjaan:

- Tambah service domain dan detail IT secara additive; jangan menghapus kolom/alur lama pada fase awal.
- Buat tim/anggota, capability superadmin/publish, policy tiket/komentar/lampiran/ekspor.
- Siapkan empat kartu layanan, filter Tiket Saya, menu operasional IT dan feature flag.
- Beri adapter kompatibilitas untuk API/kategori lama yang masih dipakai konsumen.

Selesai jika GA dan IT terpisah pada UI dan server; akses langsung URL/API lintas hak ditolak; tiga layanan GA tetap lulus regresi.

### Fase 2 — Mesin konfigurasi dan form dinamis

Pekerjaan:

- Implementasikan master layanan/katalog/kategori, form builder, registry field, versi bundle, preview dan publish.
- Bangun validator server, conditional fields, referensi master, batas file, serta visibilitas per peran.
- Seed draft katalog awal dan template Incident/Service Request; belum membuka seluruh kategori kepada pengguna.

Selesai jika superadmin dapat membuat kategori dan field baru melalui UI, preview tiga peran, publish, dan rollback; tiket lama tetap terbaca dengan schema awal; web/API menolak input yang sama-sama tidak sah.

### Fase 3 — Incident dan Service Request end-to-end

Status: **selesai teknis pada Docker local**. Pengajuan menunggu gerbang SLA/pilot. Bukti: [IT_REQUEST_PHASE_3.md](IT_REQUEST_PHASE_3.md).

Pekerjaan:

- Implementasikan triage, routing terurut/fallback, penugasan/claim, transfer, tugas, dan matriks prioritas.
- Implementasikan transisi aman, pending reason, komentar internal, resolution, konfirmasi, cancel, dan reopen.
- Implementasikan approval bersyarat dan resolver portal dengan jalur exception, revisi, serta pencegahan self-approval.
- Tambah notifikasi dan endpoint mobile menggunakan domain service yang sama.

Selesai jika contoh gangguan Wi-Fi, error aplikasi, pembuatan akun, dan instalasi software berjalan dari submit sampai closed; dua petugas tidak dapat claim tiket bersamaan; keputusan approval tidak dapat diproses dua kali.

### Fase 4 — SLA, eskalasi, dan kendali operasi

Status: **selesai teknis pada Docker local; scheduler dan worker aktif/sehat**. Target bisnis, tim dan pembukaan pengajuan mengikuti persiapan/pilot Fase 5. Bukti: [IT_REQUEST_PHASE_4.md](IT_REQUEST_PHASE_4.md).

Pekerjaan:

- Implementasikan kalender, clock respons/pemulihan/pemenuhan, snapshot kebijakan, pause/resume, breach dan eskalasi.
- Tambah antrean hampir jatuh tempo, backlog, beban tim, major incident minimum dan jadwal komunikasi.
- Aktifkan worker/scheduler beserta monitoring kegagalannya; siapkan rekonsiliasi jika worker sempat berhenti.

Selesai jika hari libur, lintas jam kerja, tiket 24×7, transfer, perubahan prioritas, reopen, retry worker dan outage scheduler menghasilkan deadline/audit yang benar tanpa notifikasi ganda.

### Fase 5 — Pilot dan rilis awal IT

Status: **gerbang, draft v4 dan UAT sintetis selesai; pilot pengguna, persetujuan SLA dan cutover belum dilakukan**. Rancangan, gerbang dan checklist: [IT_REQUEST_PHASE_5.md](IT_REQUEST_PHASE_5.md).

Pekerjaan:

- Pilot pada layanan/kategori terbatas, latih Service Desk, teknisi, approver dan superadmin.
- Jalankan migrasi percobaan, rekonsiliasi jumlah/referensi, UAT pengguna, dan evaluasi target layanan.
- Buka pengajuan IT melalui feature flag setelah gerbang rilis awal dipenuhi; dokumentasikan support dan rollback.

Gerbang rilis awal: Incident dan Service Request, konfigurasi berversi, akses terpisah, SLA/escalation, audit, API, backup/restore dan regresi GA telah diverifikasi. Ini adalah rilis cakupan awal yang selaras praktik terpilih, bukan implementasi seluruh praktik ITIL.

### Fase 6 — Problem, Change, Knowledge, aset/CI dan perbaikan layanan

Status: **berjalan**. Fondasi Problem, Change, Knowledge dan register awal aset/CI diterapkan pada Docker local; hubungan aset ke tiket, CSAT, improvement, vendor/OLA dan integrasi monitoring masih terbuka. Bukti: [IT_REQUEST_PHASE_6.md](IT_REQUEST_PHASE_6.md).

Pekerjaan:

- Tambah Problem, RCA, workaround/known error, serta hubungan incident berulang.
- Tambah model Change standard/normal/emergency, otorisasi berbasis risiko, jadwal, uji, rollback dan review.
- Tambah register aset/CI dan hubungan layanan, knowledge dengan review/publish, CSAT dan improvement records.
- Tambah pengelolaan vendor/OLA dan integrasi monitoring dengan deduplikasi serta aturan pembentukan incident.

Selesai jika satu rangkaian Incident → Problem → Change → hasil review → artikel knowledge dapat ditelusuri; pemulihan Incident tidak tergantung penutupan RCA; alert berulang tidak membanjiri antrean.

Estimasi durasi ditentukan setelah Fase 0 berdasarkan cakupan katalog, kualitas data, jumlah integrasi, dan kapasitas tim. Setiap fase menghasilkan demo yang dapat ditinjau dan tidak hanya perubahan tabel.

## 13. Strategi migrasi dan perlindungan layanan existing

1. Backup database dan file lampiran; uji restore pada lingkungan terpisah sebelum migrasi.
2. Tambah kolom/tabel baru dengan nilai kompatibel dan feature flag IT default tertutup.
3. Mapping eksplisit: `atk_rtk`, `consumption`, `ga_request_finding` tetap di layanan masing-masing. `general` ditinjau berdasarkan kategori, penerima, serta isi yang diizinkan; kasus ambigu masuk daftar review.
4. Pertahankan ID, nomor tiket, pemohon, komentar, lampiran, timestamp, approval dan audit lama. Jangan membuat ulang approval/notifikasi saat backfill.
5. Pisahkan `ticket_category_id` lama dari service domain baru; gunakan tabel mapping/adapter selama masa transisi, termasuk untuk mobile lama.
6. Tiket IT lama yang masih aktif diberi workflow awal hanya melalui aturan migrasi tervalidasi; data yang kurang tidak dipalsukan.
7. SLA historis yang tidak memiliki event lengkap diberi label tidak tersedia/hasil rekonstruksi; jangan dilaporkan seolah pengukuran asli. Target tiket aktif ditetapkan melalui kebijakan cutover yang disepakati.
8. Migrasi harus dapat dijalankan ulang dengan aman, memiliki laporan before/after, dan menangani transaksi yang terjadi saat cutover melalui jeda tulis terbatas atau rekonsiliasi delta yang teruji.
9. Rollback aplikasi menjaga kompatibilitas data additive. Bila pengajuan IT baru perlu ditutup sementara, tiket yang sudah masuk tetap tersedia bagi petugas dan pengguna sesuai hak.
10. Hapus adapter/kolom lama hanya setelah seluruh konsumen bermigrasi, observasi stabil, dan backup/pemetaan telah diverifikasi.

## 14. Verifikasi dan skenario UAT utama

| Skenario | Hasil yang diharapkan |
|---|---|
| Empat pintu layanan | Pemohon memilih layanan; detail dan tujuan tiket benar |
| Regresi tiga layanan GA | Approval, stok, serah terima, bukti, laporan, API dan notifikasi tetap berfungsi |
| Kategori baru melalui superadmin | Dapat dipakai tanpa perubahan kode jika masih dalam tipe proses/field yang didukung |
| Kondisi form dan input manipulatif | Required/opsi/visibilitas ditegakkan server; field internal tidak dapat disisipkan |
| Publish/rollback schema | Tiket lama memakai versi awal, tiket baru memakai versi aktif; draft tidak memengaruhi produksi |
| Routing ambigu/tidak cocok | Konflik terdeteksi, unmatched masuk Service Desk fallback; tidak ada tiket hilang |
| Perubahan tim, claim bersamaan | Hanya satu claim berhasil; audit dan PIC utama konsisten |
| Akses tiket sensitif | Anggota GA, pemohon lain, teknisi di luar scope dan superadmin konfigurasi tanpa hak baca ditolak |
| Approval, revisi, resolver gagal | Tidak self-approve/double-decision; revisi material meminta persetujuan ulang; exception terlihat |
| SLA kalender dan pause | Deadline sesuai kebijakan; status waiting tidak otomatis menghentikan semua clock |
| Worker gagal/retry | Eskalasi yang tertunda diproses, tanpa pengiriman ganda |
| Transfer/reprioritization/reopen | Waktu awal, siklus, deadline sebelumnya dan breach tetap dapat ditelusuri |
| Pemulihan dengan workaround | Incident dapat resolve; Problem tetap terbuka untuk investigasi |
| Change gagal | Hasil gagal/rollback tercatat; tidak otomatis ditandai berhasil |
| Konkurensi submit | Idempotency dan nomor unik mencegah duplikasi |
| Konsistensi kanal | Web/mobile/intranet menegakkan aturan dan scope yang sama |
| Migrasi dan rollback | Jumlah tiket/referensi cocok, lampiran dapat dibuka oleh pihak berhak, histori tidak berubah |

Gunakan unit test untuk evaluator aturan/kalender/policy, integration test untuk transaksi/API/portal, dan UAT untuk alur nyata serta konfigurasi superadmin. Pilot mengukur volume realistis untuk query antrean, evaluasi schema, dan worker deadline.

## 15. Ukuran keberhasilan operasional

- Kepatuhan target respons, pemulihan Incident, dan pemenuhan Request dilaporkan terpisah menurut layanan/prioritas.
- Waktu tunggu pengguna, internal dan vendor dipisahkan agar bottleneck terlihat.
- Backlog dan usia tiket, first-contact resolution, jumlah transfer, reopen rate, serta incident berulang.
- Keberhasilan pemenuhan request dan perubahan, termasuk hasil rollback/gagal.
- CSAT dengan jumlah respons dan tingkat partisipasi, bukan skor tanpa konteks.
- Tindakan perbaikan memiliki owner, target waktu, status, serta bukti evaluasi hasil.

Definisi numerator/denominator, jendela waktu, pengecualian cancelled/rejected, dan perlakuan reopen disepakati sebelum dashboard dirilis. Angka ambang serta periode pelaporan dapat diatur; definisi metrik berversi agar tren tidak berubah diam-diam.

## 16. Keputusan bisnis yang perlu ditetapkan saat desain rinci

- Nama final kartu “Permintaan & Temuan” serta katalog IT pertama yang dibuka.
- Struktur support team dan penanggung jawab tiap layanan; apakah Service Desk merupakan tim tersendiri.
- Sumber resmi role akses, struktur organisasi, approver dan service owner.
- Jam dukungan per layanan, target SLA, pengecualian pause, jalur on-call, serta penerima eskalasi.
- Kebijakan delegated request, data sensitif, retensi, dan akses audit/ekspor.
- Masa konfirmasi/auto-close/reopen dan kondisi request yang memerlukan approval ulang.
- Pemetaan Request Umum lama dan kontrak kompatibilitas API/mobile.
- Pemisahan penyusun/penerbit konfigurasi serta kewenangan otorisasi Change.

Keputusan di atas menjadi konfigurasi atau kebijakan yang terdokumentasi. Pengembangan fondasi dan prototipe dapat berjalan dengan data contoh yang diberi label; aturan operasional belum dianggap disahkan sampai pemiliknya menetapkan.

## 17. Referensi dan posisi rancangan terhadap ITIL

- [PeopleCert — ITIL 4 Incident Management](https://www.peoplecert.org/browse-certifications/it-governance-and-service-management/ITIL-1/itil4-practices-incident-management-3684): acuan tujuan pemulihan layanan.
- [PeopleCert — ITIL 4 Service Request Management](https://www.peoplecert.org/browse-certifications/it-governance-and-service-management/ITIL-1/itil4-practices-service-request-management-3690): acuan pemenuhan permintaan pengguna yang telah didefinisikan.
- [PeopleCert — ITSM Tool Capabilities and ITIL Service Delivery](https://atv.peoplecert.org/itsm-tool-capabilities-and-itil-service-delivery/): hubungan praktik dukungan, perubahan, konfigurasi, SLA, keamanan, vendor dan perbaikan.
- [PeopleCert — Accreditation Model](https://atv.peoplecert.org/accreditation/accreditation-model/): cakupan praktik yang dapat didukung alat ITSM; bukan bukti akreditasi Satset.

Model tabel, nama field, status, kemampuan superadmin, urutan fase, dan kriteria UAT dalam dokumen ini adalah keputusan desain yang diusulkan untuk Satset. ITIL digunakan sebagai panduan praktik; konfigurasi produk tetap disesuaikan dengan kebutuhan dan kemampuan organisasi.
