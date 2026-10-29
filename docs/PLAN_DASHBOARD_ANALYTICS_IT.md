# Rencana Dashboard, Statistik, dan Analitik Layanan IT

Tanggal: 28 Oktober 2026
Status: rancangan implementasi; belum membangun fitur atau mengubah akses, konfigurasi, dan data.
Acuan: [Rencana IT Request dan ITIL](PLAN_IT_REQUEST_ITIL.md), [Fase 4](IT_REQUEST_PHASE_4.md), [Fase 6](IT_REQUEST_PHASE_6.md).

> Pembaruan implementasi: dashboard lokal tahap awal tersedia di `/it/dashboard`. Fitur mencakup filter periode/jenis/prioritas/tim, ringkasan, tren, capaian clock, kategori, backlog per tim dan usia, daftar terbaru, serta ekspor CSV maksimum 5.000 baris. Pengelola konfigurasi dapat membuka dashboard, tetapi data dan ekspor tetap mengikuti scope baca tiket IT; tidak ada capability baca yang diberikan otomatis. Rumus masih merupakan usulan yang perlu disepakati service owner. Detail lanjutan seperti drill-down terfilter menyeluruh, filter layanan/kategori/PIC/status, perbandingan periode, percentile, snapshot backlog historis, registry field dinamis, XLSX/PDF dan UAT manajemen belum selesai.
>
> Audit waktu saat implementasi: timestamp tiket dan event ditulis menggunakan zona aplikasi (`APP_TIMEZONE`), sedangkan clock SLA oleh engine ditulis UTC. Query dashboard memperlakukan masing-masing sesuai asalnya, lalu menampilkan periode Jakarta. Asumsi pada definisi awal di bawah tentang seluruh data disimpan UTC harus ditinjau untuk data lama, integrasi, serta perubahan zona aplikasi; jangan melakukan backfill tanpa audit data sumber.

> Pembaruan skenario lokal: filter layanan, kategori, PIC dan status sudah diterapkan bersama filter sebelumnya. Lima kartu ringkasan membuka daftar yang mengikuti filter dan hak akses, dan tiket masuk serta selesai dibandingkan dengan periode sebelumnya yang sama panjang. CSV tetap berisi tiket masuk dan membawa filter yang sama. Drill-down grafik dan rincian clock SLA, backlog historis, PDF/XLSX, verifikasi menyeluruh serta pengesahan definisi SLA masih perlu diselesaikan. Pengujian UI lokal pada akun konfigurasi hanya menunjukkan data yang dapat dilihat akun tersebut.

## 1. Tujuan

Menyediakan ringkasan layanan IT untuk pemantauan harian dan laporan periodik, dengan angka yang dapat ditelusuri ke tiket dan definisi metrik yang konsisten. Dashboard harus membedakan Incident dan Service Request, menghitung SLA berdasarkan clock dan kalender layanan yang tersimpan, serta mengikuti hak akses pengguna pada seluruh tampilan dan ekspor.

Dashboard membantu praktik service desk, incident management, service request management, service level management, dan continual improvement. Dashboard saja tidak membuktikan sertifikasi atau kesesuaian menyeluruh terhadap ITIL.

## 2. Pemetaan kondisi existing

| Area | Sudah ada di Satset | Kebutuhan tambahan |
|---|---|---|
| Ringkasan GA | `BumInventoryController::dashboard`, `resources/views/bum/dashboard.blade.php`: filter Permintaan/Temuan, total dan status, lokasi teratas, tiket terbaru, dan ringkasan inventori. | Jadikan referensi bahasa visual, filter, kartu dan daftar; tidak menyalin logika GA yang mengambil seluruh tiket ke memori. |
| Operasional IT | `OperationsController::index` dan `resources/views/it/operations.blade.php`: tiket terbuka, mendekati jatuh tempo, breach, backlog lebih dari 7 hari, major incident, workload, dan filter daftar. | Pertahankan untuk tindakan harian; tambahkan analitik periodik dan definisi yang terdokumentasi. Nilai operasional yang ada merupakan kondisi saat ini, bukan seri sejarah. |
| Data tiket | `requests`, `it_ticket_details` menyimpan jenis, status, prioritas, klasifikasi, tim, PIC, timestamps, dan konfigurasi yang dipakai. | Rekonsiliasi tiket legacy dan data kosong; tentukan kohort, titik waktu, serta label kategori historis. |
| Riwayat | `it_ticket_events` menyimpan aksi dan perubahan status; `it_team_events` menyimpan perubahan tim. | Gunakan event untuk rekonstruksi yang benar-benar didukung; tambahkan snapshot bila rekonstruksi historis tidak memadai. Audit cakupan histori sebelum menjanjikan tren historis. |
| SLA | `it_sla_clocks`, `it_sla_spans`, `it_sla_events` menyimpan siklus, jenis clock, target, konsumsi, snapshot kalender, completion dan breach. | Agregasi per jenis clock dan siklus, serta perlakuan reopen, pengecualian dan keterlambatan worker yang jelas. |
| Form dinamis | `it_configuration_versions`, `it_ticket_details.form_answers`; field memiliki flag `reportable`. | Flag belum membuat registry/indeks laporan. Perlu aturan tipe, izin, versi, kualitas data dan pilihan nilai; jangan membuka semua jawaban dinamis. |
| Practices lanjutan | Foundation `it_practice_records`, links dan events sudah ada; Fase 6 masih memiliki pekerjaan lanjutan. | Problem/change/knowledge dilaporkan setelah alur dan data selesai serta tervalidasi; jangan menganggap seluruh Fase 6 selesai. |
| Hak akses | `ItAccess::constrainIt` dan `constrainReadable` membatasi domain, pemohon, tim dan tiket restricted; capability konfigurasi tidak otomatis memberi hak baca tiket. | Terapkan scope yang sama ke seluruh agregat, filter, pencarian, drill-down, cache dan ekspor. |

Dokumen induk memiliki catatan progres historis yang tidak mencerminkan semua pekerjaan lanjutan; rujuk dokumen fase masing-masing dan implementasi saat membangun. Data lokal/pilot tidak otomatis mewakili kondisi produksi.

## 3. Pengguna dan kebutuhan

| Pengguna | Kebutuhan | Batas awal yang diusulkan |
|---|---|---|
| Teknisi / koordinator tim aktif | Antrean, prioritas, SLA dan beban kerja yang dapat ditindaklanjuti. | Hanya tiket yang terbaca sesuai scope saat ini. |
| Service owner / manajer IT | Tren permintaan, performa SLA, layanan/kategori dominan dan backlog. | Butuh akses laporan yang ditetapkan eksplisit serta scope tiket yang sesuai; jabatan atau hak konfigurasi saja tidak cukup. |
| Auditor / pimpinan | Laporan periodik terdefinisi dan dapat ditelusuri. | Beri akses eksplisit sesuai mandat; ekspor dan angka tunduk pada scope yang sama. |
| Pemohon | Memantau tiketnya sendiri. | Tetap melalui Tiket Saya; tidak otomatis mendapat analitik tim. |

Keputusan awal: gunakan visibility `ItAccess` yang ada untuk setiap query. Jika diperlukan laporan agregat lintas tim, rancang capability khusus laporan serta scope terpisah dan proses pemberiannya; **jangan** otomatis memberikan `view_it_queue` atau `view_restricted_it`, termasuk kepada akun konfigurasi ID 1/31. Angka yang didasarkan pada sebagian tiket harus diberi label **sesuai akses Anda**. Tidak ada total global yang dapat disimpulkan dari selisih filter; gunakan drill-down terbatas dan pertimbangkan supresi kelompok kecil bila agregat lintas scope nantinya disetujui.

## 4. Informasi dan pengalaman pengguna

Usulan menu **Layanan IT → Dashboard & Laporan**, terpisah dari Antrean Operasional; tombol menuju antrean dan daftar tiket tetap tersedia sesuai izin.

1. Baris judul dan penjelasan scope, waktu pembaruan serta zona waktu.
2. Filter periode, jenis tiket, layanan, kategori, prioritas, tim, PIC dan status; filter hanya menawarkan nilai yang relevan dengan scope. Sediakan reset dan filter yang tetap terlihat saat ekspor.
3. **Ringkasan**: tiket masuk periode, tiket diselesaikan periode, backlog saat ini, SLA respons dan pemulihan/pemenuhan. Tampilkan jumlah dasar dan `—` bila tidak ada data yang layak dihitung.
4. **Tren**: tiket masuk vs diselesaikan per hari/minggu/bulan; pemisahan Incident dan Service Request; distribusi layanan/kategori/prioritas.
5. **SLA & backlog**: capaian masing-masing clock, jumlah mendekati jatuh tempo/breach saat ini, kelompok umur backlog dan tiket yang memerlukan perhatian. Angka historis backlog hanya bila tersedia snapshot/rekonstruksi yang tervalidasi.
6. **Tim & workload**: tiket aktif per tim/PIC dan distribusi kategori; hindari ranking produktivitas individu tanpa konteks kompleksitas dan kewenangan.
7. **Daftar terfilter**: jumlah, status, prioritas, layanan, tim, tanggal relevan dan tautan detail sesuai hak; pagination, empty state, loading, pesan error dan akses keyboard. Klik grafik/kartu membawa filter yang sama ke daftar.
8. **Laporan**: ekspor data yang terbaca dan ringkasan periodik; tunjukkan filter, periode, scope, waktu pembuatan, versi definisi dan status ketersediaan data.

Tata letak mengikuti gaya GA tetapi dibuat bersih, responsif, spasi konsisten, label jelas dan warna bukan satu-satunya penanda. Grafik dilengkapi angka/tabel yang dapat diakses; gunakan hanya komponen/chart library yang sudah tersedia di proyek. Jangan menampilkan judul/isi tiket rahasia ke pengguna tanpa izin.

## 5. Kontrak metrik yang diusulkan untuk MVP

**Semua definisi di bawah adalah usulan untuk disetujui service owner sebelum penerapan, bukan SLA aktual organisasi.** Filter periode berlaku pada tanggal metrik yang ditentukan; gunakan zona Asia/Jakarta sebagai default tampilan, simpan dan bandingkan UTC. Rentang inklusif tanggal lokal diterjemahkan sebagai `[awal, awal hari setelah akhir)` dalam UTC. Tampilkan angka pembilang/penyebut; penyebut nol menghasilkan `—`, bukan 0% atau 100%.

| Metrik | Definisi dan tanggal | Catatan |
|---|---|---|
| Tiket masuk | Jumlah tiket IT unik dengan waktu submit dalam periode. | Pisah incident/service request; jangan hitung tiket GA. Validasi sumber waktu submit; tiket tanpa data runtime dimasukkan hanya bila lolos aturan legacy yang disepakati. |
| Diselesaikan | Jumlah tiket unik dengan event transisi `resolved` pada periode. | Setiap tiket dihitung satu kali per periode meskipun resolve ulang; jelaskan bahwa tren event bukan status saat ini. Gunakan waktu event, bukan `resolved_at` yang dapat dikosongkan saat reopen. |
| Backlog saat ini | Tiket unik yang saat ini tidak `resolved`, `closed`, `cancelled`, atau `rejected`, per waktu pembaruan. | Kartu snapshot saat ini, tidak dipengaruhi periode tanggal; filter dimensi tetap berlaku. Tidak mengklaim kondisi masa lalu. |
| Umur backlog | Selisih waktu dinding sejak submit untuk tiket backlog saat ini, dikelompokkan mis. 0–1, >1–3, >3–7, >7 hari. | Ini usia kalender, bukan jam kerja SLA; label dan batas eksplisit. |
| SLA respons | Clock `response` yang selesai tepat target ÷ seluruh clock respons selesai yang memenuhi kriteria pada periode `completed_at`. | Hitung per clock/siklus, tampilkan N; tentukan aturan pengecualian sebelum rilis. |
| SLA pemulihan / pemenuhan | Formula yang sama untuk `restore` pada incident dan `fulfil` pada service request; tampilkan terpisah. | Clock `running`/`paused` belum masuk penyebut capaian selesai; tidak mencampurkan jenis layanan. |
| SLA berisiko / terlambat saat ini | Tiket backlog dengan clock aktif pada siklus terkini yang memenuhi aturan near-due/breach. | Hitung tiket unik, bukan jumlah clock. Untuk near-due gunakan aturan yang sama dengan operasional (default 30 menit); breach mengikuti state/event yang tervalidasi, pause dan reconciliation. Jelaskan bila freshness worker tidak sehat. |
| Waktu respons / pemulihan | Median dan P90 `consumed_seconds` untuk clock selesai sesuai jenis dan periode completion. | Ini waktu layanan sesuai kalender/pause dalam snapshot, bukan durasi dinding. Tampilkan jumlah sampel dan metode percentile. |
| Reopen | Jumlah tiket unik dengan event reopen pada periode; bila metrik rasio dibutuhkan, tetapkan kohort dan denominator terpisah. | Jangan hitung ulang backlog dan SLA seolah siklus lama hilang. |
| Distribusi jenis/kategori/prioritas | Tiket unik masuk pada periode, dikelompokkan berdasar dimensi yang eksplisit. | Tandai apakah klasifikasi saat ini atau snapshot submit; tidak menampilkan label yang terhapus tanpa fallback key/versi. |
| Workload | Tiket backlog saat ini, dikelompokkan tim/PIC saat ini; sertakan belum ditugaskan. | Tidak membuktikan produktivitas, dan tidak boleh menghilangkan tiket tanpa tim/PIC melalui inner join. |

### Kebijakan perhitungan yang perlu diputuskan

- **Kohort SLA:** usulan clock selesai berdasarkan `completed_at`; laporkan siklus, bukan tiket unik, karena reopen dapat membuat clock baru. Sebagai pelengkap tampilkan jumlah tiket unik. Keputusan final harus mendefinisikan clock dibatalkan, reject/cancel, target/priority berubah, transfer dan clock yang belum selesai; tampilkan jumlah yang dikecualikan.
- **Ketepatan SLA:** gunakan fakta breach dan konsumsi terhadap target sesuai implementasi engine, termasuk waktu yang dibawa saat reopen; jangan hanya membandingkan `completed_at` dengan `due_at` tanpa memahami pause, jadwal dan perubahan. Verifikasi dengan `SlaEngine` dan fixture. Bila data tidak cukup, tandai tidak diketahui dan keluarkan dari penyebut disertai jumlahnya.
- **Historis:** `resolved_at`/`closed_at`, tim, PIC, prioritas dan status saat ini dapat berubah. Event/snapshot yang tidak memadai tidak boleh diinterpretasikan sebagai fakta masa lalu. Versi konfigurasi tiket dipakai untuk label klasifikasi ketika tersedia; bila tidak tersedia, tampilkan fallback dan indikator kualitas.
- **Legacy:** tiket tanpa `it_ticket_details`, clock, klasifikasi atau event tidak boleh diam-diam dianggap patuh SLA. Definisikan bagian yang dapat disertakan pada total dan keluarkan dari perhitungan yang tidak dapat dibuktikan, dengan penghitung `data tidak lengkap`.
- **Definisi berversi:** simpan kamus metrik, periode efektif dan perubahan agar laporan lama dapat dijelaskan. Jangan mengubah rumus tanpa penandaan; bila agregat disimpan, simpan versi definisi dan timestamp perhitungan.

## 6. Pelaporan dan ekspor

- MVP: layar dashboard dan drill-down daftar terfilter; ekspor CSV sesuai scope dengan kolom terpilih dan mitigasi CSV formula injection. Tampilkan konteks filter, periode, zona waktu, versi definisi, freshness, scope dan jumlah baris; pisahkan ringkasan dari detail.
- Tahap berikut: XLSX dan PDF ringkasan manajemen jika dibutuhkan. Ekspor besar memakai job antrian, berkas privat, masa berlaku, audit unduh dan pemeriksaan izin ulang saat unduh. Jangan memakai disk public untuk berkas sensitif.
- Laporan default: ringkasan bulanan, SLA per jenis clock dan prioritas, tren kategori/layanan, backlog dan beban tim. Filter yang sama menghasilkan angka yang sama pada layar dan ekspor, dengan batas waktu snapshot yang dinyatakan.
- Jadwal pengiriman otomatis, penerima eksternal, akses agregat lintas scope dan isi tiket restricted bukan bagian MVP; perlu keputusan dan otorisasi tersendiri.

## 7. Field dinamis dan konfigurasi superadmin

Tahap awal gunakan dimensi standar dan **tidak** langsung menjadikan seluruh field `reportable` sebagai filter publik. Tahap lanjutan rancang registry pelaporan berversi: field key + form/version, label historis, tipe yang didukung, pemetaan nilai, hak dan apakah boleh difilter/dikelompokkan/diekspor. Batasi pada tipe terstruktur yang aman; teks bebas, file, nilai sensitif dan field internal dikecualikan kecuali disetujui melalui kebijakan tersendiri. Jawaban lama, field yang berganti tipe dan key yang dipakai ulang membutuhkan aturan migrasi yang eksplisit.

Konfigurasi yang layak dinamis setelah MVP: rentang default, widget yang tampil, urutan tampilan, threshold operasional yang disetujui, serta registry field. Definisi metrik, scope keamanan, izin dan penghapusan audit tidak boleh berubah tanpa kontrol versi dan otorisasi yang sesuai. Pengaturan tampilan tidak memberikan hak melihat data tambahan.

## 8. Rancangan teknis

- Tambahkan route/controller khusus dashboard dan reporting IT, service query terpusat, policy/guard akses dan view Blade dengan komponen visual konsisten GA. Gunakan helper `ItAccess::constrainIt` + `constrainReadable` sebagai titik awal query; semua subquery, joins, filter choices, drill-down dan ekspor harus berasal dari himpunan tiket berizin yang sama.
- Hitung agregat di database, bukan memuat semua tiket seperti pola GA saat ini. Gunakan indeks sesuai query dan hasil `EXPLAIN`; hindari `pluck` semua ID untuk volume besar. Pertimbangkan pagination serta rentang maksimum filter yang proporsional.
- Batasi jumlah query; uji performa dengan data representatif dan tetapkan anggaran latensi pada Fase D0. Cache hanya setelah scope diuji: key harus mencakup user/scope, izin, filter, versi definisi dan freshness; invalidasi setelah perubahan akses/keanggotaan, atau jangan cache data terproteksi bila invalidasi belum andal.
- Basis tren backlog: evaluasi event aktual terlebih dahulu. Jika historis tidak dapat direkonstruksi dengan benar, mulai snapshot harian berizin/desain agregasi yang aman sejak tanggal fitur aktif; beri label coverage dan jangan membuat data sebelum coverage. Penjadwal idempotent, monitor kegagalan, backfill hanya dari fakta yang terverifikasi.
- Waktu pembaruan menampilkan waktu data terhitung, health worker dan keterlambatan bila ada. Query angka SLA tidak boleh memperlakukan clock overdue yang belum direkonsiliasi sebagai pasti aman; definisikan fallback dan uji pause.
- Catat penggunaan ekspor dan perubahan konfigurasi laporan tanpa mencatat isi sensitif; tidak menambah akses atau menyebarkan laporan secara otomatis.

## 9. Tahapan implementasi dan gerbang

| Tahap | Pekerjaan | Selesai jika |
|---|---|---|
| D0 — Kesepakatan & audit | Inventaris data/status event aktual, rekonsiliasi legacy, contoh fixtures; sepakati rumus, cakupan, peran, zona, periode, SLA eligibility, akses dan sasaran kinerja. | Kamus metrik disetujui; known gaps dan cakupan historis dicatat; dataset uji berisi reopen, breach, pause, cancel/reject, transfer dan restricted. |
| D1 — Dashboard MVP | Query terpusat dan akses; kartu volume/backlog, tren masuk vs selesai, distribusi, daftar dan drill-down; desain mengikuti GA. | Angka direkonsiliasi dengan fixture dan daftar; semua filter dan empty/error states bekerja; uji isolasi scope lulus. |
| D2 — SLA & analitik | Tambahkan clock terpisah, breach, umur backlog, percentiles, workload dan freshness; coverage snapshot jika dibutuhkan. | Hasil sesuai engine dengan uji kalender 24×7/jam kerja, pause, siklus reopen, worker tertunda, dan N/penyebut nol. |
| D3 — Laporan & ekspor | CSV, metadata filter/scope/definisi, audit; XLSX/PDF dan job privat bila disepakati. | Layar dan ekspor cocok untuk snapshot sama; akses ulang, injection dan restricted diuji; beban besar tidak membebani request. |
| D4 — Dynamic reporting & lanjutan | Registry field terkontrol, konfigurasi widget dan indikator Problem/Change/Knowledge setelah praktik siap. | Versioning/izin dan perubahan tipe field teruji; metrik practices hanya aktif bila data dan prosesnya terbukti lengkap. |
| D5 — UAT & rilis | UAT service owner/koordinator/auditor dengan data representatif, regresi GA dan IT, dokumentasi serta rollout bertahap. | Persetujuan definisi dan UAT terdokumentasi; deployment, rollback dan monitoring disiapkan. |

D0–D2 dapat dilaksanakan sebelum seluruh Fase 6 selesai, selama prasyarat alur tiket dan SLA tersedia. Implementasi atau pengujian lokal tidak berarti fitur siap/published di production.

## 10. Rencana pengujian dan penerimaan

- Fixture numerik tervalidasi manual untuk periode batas hari Jakarta/UTC, tanggal tanpa data, penyebut nol, status terbuka/selesai, resolved dua kali setelah reopen, cancel/reject, tiket legacy/kurang data, beberapa clock dan jenis layanan.
- Test role: pemohon, anggota tim, koordinator, konfigurator tanpa akses tiket, akses antrean global tanpa restricted, dan pembaca restricted. Pastikan kartu, filter, daftar, API, cache dan ekspor tidak membocorkan data atau membedakan tiket tersembunyi.
- Test SLA berdasar snapshot kalender, consumed/carried seconds, breach, pause, transfer, prioritas berubah, reopen, clock berhenti dan keterlambatan rekonsiliasi; rekonsiliasi terhadap engine bukan rumus perkiraan.
- Test drill-down dan ekspor mempertahankan filter dan scope; jumlah dataset sama pada waktu snapshot yang sama. Verifikasi formula injection dan privasi file.
- Test viewport desktop dan mobile, keyboard, fokus, label, kontras, tanpa mengandalkan warna; grafik memiliki alternatif tabel. UAT mencakup skenario pengguna sebenarnya.
- Uji query pada dataset besar, `EXPLAIN`, pagination, batas waktu dan penggunaan memori; pastikan dashboard GA, antrean IT dan alur tiket tetap berjalan.

## 11. Keputusan yang dibutuhkan sebelum realisasi

1. Pemilik produk dan penanggung jawab persetujuan definisi metrik/SLA.
2. Siapa boleh melihat laporan lintas tim dan apakah laporan tiket restricted memerlukan scope terpisah; hak konfigurasi tetap bukan hak baca.
3. Kohort capaian SLA, aturan clock dibatalkan/tidak lengkap, reopening, transfer, perubahan prioritas dan target.
4. Periode default, kebutuhan perbandingan periode, kapan dan bagaimana backlog historis mulai tersedia.
5. Kolom ekspor, format prioritas, retensi dan audit; kebutuhan laporan terjadwal bila ada.
6. Definisi dimensi historis serta daftar field dinamis yang aman untuk reporting dan pemilik persetujuannya.
