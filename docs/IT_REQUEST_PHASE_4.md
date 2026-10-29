# IT Request — Penyelesaian Fase 4

Tanggal: 28 Oktober 2026. Status: **implementasi, migrasi, dan runtime Docker local selesai**. Acuan: [PLAN_IT_REQUEST_ITIL.md](PLAN_IT_REQUEST_ITIL.md), Fase 4. Pengajuan untuk pengguna tetap tertutup sampai konfigurasi bisnis dan pilot Fase 5 disetujui.

## Hasil implementasi

| Area | Hasil |
| --- | --- |
| Konfigurasi SLA | Bundle schema 3 menambahkan kalender, kebijakan terurut/fallback, target per prioritas/jenis clock, pause, ambang, penerima dan pengingat eskalasi. Schema 1–2 tetap terbaca. |
| Kalender | Zona waktu IANA, jadwal mingguan dengan beberapa interval, libur, pengecualian tanggal, dan 24×7. Deadline dan konsumsi disimpan dalam UTC; tampilan tiket memakai Jakarta. |
| Clock terpisah | Respons manusia pertama, pemulihan Incident, dan pemenuhan Service Request. Claim, assignment, start dan notifikasi otomatis tidak menghentikan clock respons. |
| Snapshot | Tiket mengacu pada konfigurasi immutable. Setiap clock juga menyimpan salinan kebijakan/kalender dan checksum sehingga target historis dapat dijelaskan. |
| Pending | Kode menunggu wajib untuk profil baru. Pause diterapkan hanya pada clock dan alasan yang disetujui kebijakan; vendor/internal tidak otomatis mendapat pause. |
| Prioritas/transfer | Waktu yang sudah terpakai dipertahankan. Perubahan prioritas memakai target dari snapshot dan menghitung ulang deadline dari rentang layanan yang benar; breach lama tetap tercatat. Transfer tidak memulai ulang SLA. |
| Reopen | Siklus SLA baru terhubung melalui `previous_clock_id`; hasil siklus lama dipertahankan. Budget pemulihan/pemenuhan dapat memakai sisa atau target penuh sesuai konfigurasi. Respons siklus baru dihitung tersendiri. |
| Breach/eskalasi | Audit warning, breach, level eskalasi dan slot pengingat memakai kunci unik. Rekonsiliasi setelah outage mencatat deadline asli dan mengirim slot pengingat terkini, tanpa membanjiri pengguna dengan seluruh pengingat yang terlewat. |
| Antrean operasional | Tiket aktif, mendekati deadline, breach, backlog > 7 hari, beban tim/PIC/prioritas, serta major incident; agregat mengikuti hak baca tiket. |
| Major incident | Penunjukan koordinator komunikasi, interval dan jadwal pembaruan, linked incidents, komentar pembaruan, peringatan komunikasi terlambat, dan review pascapemulihan. Pemulihan tiket tidak bergantung pada penyelesaian review. |
| Delivery | Outbox transaksi, antrean database IT tersendiri, retry, pengecekan ulang akses penerima, dan satu inbox operasional per delivery. |
| Runtime | Container `it-worker` dan `it-scheduler` aktif, heartbeat alami terverifikasi, healthcheck Docker dan halaman monitoring tersedia. |

## Cara mengatur SLA

1. Buka Konfigurasi IT → draft → **SLA & kalender**. Tambahkan profil SLA pada draft yang memerlukan schema 3.
2. Tinjau kalender. Jam Senin–Jumat 08:00–17:00 Asia/Jakarta hanya template draft, bukan jam dukungan yang disahkan. Tidak ada angka target SLA bisnis yang diisi otomatis.
3. Tambahkan kebijakan Incident dan Service Request, kalender, target respons/pemulihan/pemenuhan untuk P1–P4, serta pause per clock.
4. Susun kebijakan khusus lebih dahulu dan kebijakan umum/fallback di bawahnya. Pilih fallback eksplisit untuk setiap jenis tiket aktif.
5. Tentukan warning dan level eskalasi: persentase target, interval pengingat bila perlu, peran penerima dan pengguna tambahan. Penerima tetap harus memiliki akses penanganan tiket; pemilihan penerima tidak memberi capability baru.
6. Simpan draft dengan alasan. Gunakan **Simulasi deadline tersimpan** untuk katalog, prioritas dan waktu mulai. Preview menggunakan kalender server; tidak membuat tiket atau menjalankan resolver/pengiriman.
7. Publikasi menolak layanan tanpa owner/tim aktif, fallback SLA yang hilang, referensi tidak valid, target belum lengkap, atau interval kalender yang bertumpang tindih. Publikasi tidak membuka pengajuan.

Target diuji dengan angka sintetis hanya di fixture/hasil restore. Tidak ada angka SLA yang disahkan atau dipublikasikan oleh penerapan ini.

## Aturan perhitungan

Clock mulai dari pelaporan pada submit. Konsumsi berupa detik layanan dalam kalender snapshot, bukan selisih waktu dinding untuk semua layanan. Mode 24×7 tetap menghitung libur dan akhir pekan. Kalender kerja menghitung irisan interval dukungan; pengecualian tanggal menggantikan jadwal mingguan/libur pada tanggal tersebut.

Respons manusia selesai ketika petugas yang berwenang mengirim respons/komentar publik, pembaruan major incident publik, atau penyelesaian. Catatan internal dan tindakan otomatis tidak dianggap respons. Respons oleh pemohon sendiri tidak dihitung sebagai respons petugas.

Ketika pending/approval/revisi, setiap clock mengevaluasi daftar pause miliknya. Clock paused menampilkan sisa budget dan tidak menjanjikan deadline pasti sebelum resume. Resume memproyeksikan deadline berdasarkan sisa budget dan kalender.

Rentang waktu berjalan dicatat pada `it_sla_spans`; rentang bersebelahan digabung. Karena itu prioritas yang dinaikkan setelah melewati target baru tetap menghasilkan deadline historis yang tepat, termasuk jika pernah ada jeda. Breach dihitung jika konsumsi melampaui budget; penyelesaian tepat pada batas masih memenuhi target. Hasil breach tidak dihapus saat prioritas diturunkan, tiket ditransfer, pending, dibatalkan atau ditolak.

Reopen menyimpan siklus lama dan membuat siklus baru. Untuk mode sisa budget, konsumsi pemulihan/pemenuhan terdahulu dibawa ke siklus baru; waktu ketika tiket sudah resolved/closed tidak dimasukkan. Untuk mode penuh, target siklus baru diperbarui secara eksplisit oleh kebijakan, tanpa menghapus hasil siklus lama.

Snapshot lama tanpa clock SLA tidak diadopsi otomatis dan tidak diberi pencapaian historis buatan. Cutover tiket lama memerlukan keputusan migrasi/pilot tersendiri.

## Runtime, recovery dan monitoring

`it-scheduler` menjalankan Laravel `schedule:work`. Setiap menit `satset:it-reconcile` memproses tiket IT runtime, memeriksa SLA dan komunikasi major, mendispatch outbox, serta mengirim job heartbeat. Scheduler memakai `withoutOverlapping`; transaksi dan kunci unik tetap menjaga konsistensi jika dua proses bertemu. Satu tiket bermasalah dilaporkan sebagai kegagalan scheduler tetapi tidak menghentikan pemrosesan tiket lainnya.

`it-worker` menjalankan koneksi `it_database`, queue `it`, timeout worker 60 detik, maksimal 5 percobaan dan recycle proses setiap jam. Job pengiriman mempunyai timeout 45 detik; koneksi antrean memiliki `retry_after=120`. Worker memakai `it_queue_jobs` dan `it_queue_failed_jobs`; tidak mengambil job GA/default.

Outbox pending akan diantrikan kembali. Outbox queued yang belum selesai setelah 5 menit dapat diantrikan ulang untuk memulihkan dispatch/job yang hilang. Job boleh dieksekusi lebih dari sekali; transaksi, status delivery dan unique `delivery_id` memastikan **satu pesan inbox**. Penerima yang kehilangan akses dilewati dan inbox juga memeriksa ulang akses saat dibaca.

Setelah retry habis, delivery berstatus failed dan terlihat pada monitoring. Operator dapat menjalankan:

| Perintah Artisan | Tujuan |
| --- | --- |
| `satset:it-health` | Status gabungan scheduler, worker, kegagalan dan backlog |
| `satset:it-health --component=worker --max-age=180` | Healthcheck satu komponen |
| `satset:it-reconcile` | Rekonsiliasi segera setelah pemulihan outage |
| `satset:it-retry ID --reason="Alasan perbaikan"` | Retry satu delivery gagal dengan audit operator |
| `satset:it-ack-failure UUID --reason="Alasan penanganan"` | Tandai kegagalan job yang sudah ditangani; bukti tetap disimpan dan worker harus sehat |

Delivery yang berhasil setelah retry menandai riwayat kegagalan terkait sebagai terselesaikan. Riwayat tetap disimpan. Acknowledgement job tidak menyembunyikan outbox yang masih failed.

Halaman **Konfigurasi IT → Status worker** (`/it/operations/health`) memantau heartbeat, job gagal, serta pesan tertunda/tertahan. Heartbeat > 180 detik, kegagalan scheduler, job gagal yang belum terselesaikan, atau outbox tertahan > 10 menit membuat status gabungan tidak siap. Hak konfigurasi hanya memberi pemantauan infrastruktur, bukan isi tiket/agregat operasional.

Kanal delivery Fase 4 adalah inbox web/API Satset. Jaminan deduplikasi di atas berlaku pada inbox database. Push/email eksternal belum diaktifkan; integrasi kanal eksternal dan kontrak idempotensinya harus diverifikasi saat pilot sebelum menjanjikan jaminan yang sama pada provider lain.

## Endpoint dan akses

- Web `/it/operations`: dashboard dengan `filter=all|near_due|breached|backlog|major`; default mendekati jatuh tempo 30 menit, dapat diubah melalui parameter `minutes`.
- Web `/it/operations/health`: monitoring untuk pengelola konfigurasi atau operasi.
- Mobile: path yang sama di bawah `/api/mobile/v1/satset/it` menggunakan bearer token existing.
- Detail tiket menambahkan daftar clock/siklus/deadline. Staff juga menerima major incident, tautan berotorisasi dan audit SLA.
- Tindak lanjut melalui domain service yang sama: `respond`, `priority`, `major_start`, `major_update`, `major_link`, `major_end`, serta `pending` dengan `pending_code`.
- Preview administratif: `POST /it/configuration/versions/{version}/sla-preview` dengan `catalog_key`, `priority`, `started_at` ISO 8601; padanan mobile memakai prefix `/api/mobile/v1/satset`. Preview tetap membutuhkan capability konfigurasi.

Koordinator hanya dapat menautkan incident yang berhak ditangani. Linked incident tidak mendapat hak baca parent secara otomatis, tidak ditutup otomatis saat parent pulih, dan link tidak membuka tiket yang kemudian menjadi tidak terbaca. Penetapan major pada Service Request atau incident yang sudah menjadi child ditolak.

## Bukti verifikasi dan penerapan

- **176 test / 840 assertions** lulus, termasuk regresi GA serta kalender, timezone/DST, pause, approval wait, prioritas, transfer, reopen, deduplikasi, recovery job, scope dashboard, major incident dan isolasi tiket yang gagal direkonsiliasi.
- **45 pemeriksaan MySQL** lulus pada hasil restore terpisah.
- Claim/approval simultan tetap menghasilkan satu pemenang. Dua proses rekonsiliasi SLA memakai koneksi MySQL terpisah; hanya satu breach per clock dan satu outbox per event/penerima.
- Worker database sungguhan memproses outbox di salinan; satu inbox per delivery, antrean habis, dan job duplikat tidak membuat pesan tambahan.
- Target contoh pada empat katalog tetap berjalan sampai closed; deadline lampau akibat perubahan prioritas, pause/resume, siklus reopen, major incident dan kalender libur diverifikasi pada MySQL.
- Tab SLA/kalender diperiksa melalui Chrome local. Seluruh form workflow/dashboard/preview juga diuji melalui HTTP. Draft pengguna yang sedang dibuka tidak dipublikasikan oleh agen.
- `node --check` pada builder dan `git diff --check` lulus.

Backup privat: `storage/app/private/it-phase01/baseline-20261028T100227Z/`. Uji restore ke `satset_it_verify_20261028100455` cocok untuk **62 tabel / 7 file**. Fixture MySQL dibersihkan; hash data existing cocok. Migrasi `2026_10_28_000005_create_it_sla_operations` diterapkan ke source dengan **60 tabel existing di luar migrations/sessions tetap cocok**.

Bukti privat: `restore-verification.json`, `phase04-mysql-verification.json`, `phase04-deployment.json`, `phase04-runtime-status.json`, `phase04-runtime-final.json`. Status runtime membuktikan heartbeat berasal dari container `scheduler` dan `worker`, bukan heartbeat manual dari proses verifikasi.

Kondisi saat pemeriksaan pascapenerapan: 15 request, 3 draft, versi aktif null, nol clock/outbox/tiket major nyata, capability ID 1/31 tetap konfigurasi/publikasi saja, dan `satset.it.requests_enabled=false`. Kedua container sehat, antrean dan kegagalan nol. Tidak ada tim atau target bisnis yang diaktifkan otomatis.

## Lanjutan Fase 5 dan recovery aman

Fase 5 menetapkan owner, anggota/koordinator tim, jam dukungan, target SLA, penerima eskalasi dan kategori pilot; memublikasikan bundle lengkap; menjalankan UAT dan rekonsiliasi cutover; kemudian membuka feature flag jika gerbang rilis terpenuhi. Pilot wajib memakai schema 3 dengan SLA yang telah disetujui dan tidak membiarkan rollback ke profil tanpa SLA membuka pengajuan.

Jika runtime perlu dihentikan, hentikan hanya service `it-worker`/`it-scheduler`, pertahankan pengajuan tertutup, dan simpan tabel/event/outbox untuk recovery. Setelah perbaikan, jalankan rekonsiliasi dan pantau health. Jangan menjatuhkan tabel SLA untuk membatalkan penerapan jika sudah ada data operasional; uji pemulihan pada database terpisah dan lakukan rekonsiliasi sebelum keputusan restore source.
