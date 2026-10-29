# Persiapan rilis production layanan IT Satset

Tanggal pemeriksaan lokal: 29 Oktober 2026
Status: **belum dapat dinyatakan siap production**. Akses production atau staging belum tersedia. Dokumen ini adalah rencana dan daftar pemeriksaan, bukan bukti deployment atau persetujuan go-live.

## Ruang lingkup dan hasil lokal

Rilis yang ditinjau mencakup pemisahan layanan IT, klasifikasi tiket dan perlindungan akses, konfigurasi, workflow, SLA, dashboard awal, praktik IT awal, migrasi, queue dan scheduler. Ia juga menyentuh controller, model, middleware dan backup tiket yang dipakai GA. Sebagian dashboard lanjutan dan integrasi direktori karyawan Portal belum selesai. Jangan menyebutnya selesai dalam catatan rilis.

Pada 29 Oktober, suite lokal selesai **202 tes / 1.013 assertion**. Pemeriksaan sintaks JavaScript dan file PHP yang diperbaiki serta `git diff --check` lulus. Pengujian ini tidak membuktikan kecocokan schema, data, lingkungan atau deployment production. Screenshot dan UAT lokal bukan bukti uji pengguna production.

Dua perbaikan hasil audit lokal:

- Inisialisasi draft konfigurasi di production tidak lagi mengasumsikan pengguna ID 1. Tetapkan `SATSET_IT_BOOTSTRAP_ACTOR_ID` secara eksplisit ke akun Satset yang telah diverifikasi sebelum menjalankan `ItConfigurationSeeder`. Pengaturan ini mencatat aktor draft, **tidak** memberi capability atau hak baca tiket. Lingkungan non-production tetap mendukung fallback lokal ID 1 bila variabel tidak ditetapkan. Jika set konfigurasi sudah ada, seeder tidak menggantinya.
- Pilihan tahun di dashboard umum sekarang menggunakan scope tiket yang sama dengan statistiknya, sehingga tahun yang hanya ada pada tiket IT terbatas tidak terbuka melalui filter.

## Gerbang sebelum membuat paket push

- [ ] Tinjau semua perubahan tracked dan untracked, termasuk perubahan lintas GA dan tiket lama, file migrasi awal users yang dipindahkan ke `database/legacy`, script verifikasi serta file dokumen. Pilih cakupan rilis secara eksplisit. `git diff --stat` tidak mencakup file untracked.
- [ ] Pastikan tidak ada `.env`, backup, file pribadi, log atau data verifikasi yang ikut masuk commit. Jangan menjalankan script cutover lokal terhadap production. Script di `scripts/it_phase*_ops.py` khusus baseline lokal dan bukan prosedur deployment umum.
- [ ] Jalankan ulang suite lengkap dan pemeriksaan syntax pada commit rilis yang benar. Siapkan review perubahan sebelum push. Pengujian setelah commit diperlukan bila isi paket berubah.
- [ ] Tetapkan pemilik deployment, jadwal, titik henti, kanal komunikasi, dan siapa yang dapat menghentikan atau memulihkan layanan.

## Audit target production read only

Lakukan dengan akun operator yang berwenang tanpa mencetak secret, token, isi tiket atau data pribadi ke laporan. Simpan bukti pada lokasi privat.

1. Catat commit/release yang sedang berjalan, versi PHP, dependency dan mekanisme deploy yang benar untuk aplikasi, scheduler dan worker. Pastikan storage privat tetap tersedia setelah deploy dan permission dapat dibaca/ditulis oleh proses yang sesuai.
2. Periksa status migration: `php artisan migrate:status`. Bandingkan seluruh migration pending dengan riwayat/schema aktual, bukan asumsi bahwa production identik dengan Docker lokal. Khususnya periksa migration starter users yang dipindahkan, migration users domain, foreign key, indeks, tipe JSON dan tabel/kolom yang akan dibuat. Hentikan bila ada drift atau migrasi lama yang tidak dipahami.
3. Dengan kode kandidat tersedia di staging atau salinan production, jalankan `php artisan satset:it-audit --json` untuk menghitung kelompok legacy dan pengecualian tanpa mencetak isi tiket. Audit semua kategori `general`/unknown dan kandidat IT dengan pemilik data sebelum mapping. Hindari mengubah domain lama berdasarkan tebakan, sebab identifikasi memengaruhi akses.
4. Periksa apakah `SATSET_IT_REQUESTS_ENABLED` secara efektif `false`, bukan hanya nilai berkas. Pastikan cache konfigurasi, web, scheduler dan worker memakai versi dan nilai konsisten. Jangan mencetak seluruh konfigurasi atau secret. Konfirmasi integrasi approval Portal/SSO tanpa menampilkan nilai `LRTJ_SPACE_SATSET_SHARED_SECRET`.
5. Pastikan kelengkapan backup database dan seluruh file privat/public yang relevan, lalu **uji restore terisolasi** pada salinan yang tidak menunjuk ke production. Cocokkan schema, jumlah, referensi dan file, lalu verifikasi delta transaksi sebelum cutover. ZIP tiket lama tidak mencakup data IT lengkap dan akan menolak operasi bila tiket IT terdeteksi.
6. Verifikasi deployment mendukung queue `it_database`, tabel `it_queue_jobs`, failed table `it_queue_failed_jobs` untuk proses IT, scheduler, penyimpanan bersama dan kesehatan worker. Compose lokal adalah contoh lokal, bukan bukti topologi atau instruksi production. Periksa supervisor/orchestrator, cache dan proses yang benar pada target.
7. Pastikan admin konfigurasi dan pemilik layanan ditentukan berdasarkan identitas production yang diverifikasi. ID lokal 1/31 tidak boleh diasumsikan sama di production. Pemberian capability harus eksplisit dan tidak otomatis menambah akses global/restricted.

## Latihan pada staging atau restore terisolasi

- Terapkan migration pada salinan yang telah direstore menggunakan prosedur target dan cek data lama sebelum/sesudah. Jangan menjalankan seed keseluruhan atau migrasi destruktif tanpa review. `ItFoundationSeeder` membuat tim draft bila belum ada. `ItConfigurationSeeder` membuat konfigurasi draft bila belum ada dan tidak mengaktifkan layanan.
- Validasi akses IT terbatas, endpoint lama, layanan GA (permintaan/temuan, konsumsi, ATK/RTK), approval Portal, notifikasi dan aplikasi mobile. Periksa hak role, lampiran privat, kebocoran daftar/agregat, dan hasil ketika pengguna tidak boleh mengajukan.
- Verifikasi dashboard, filter, rincian dan CSV pada data sintetis, termasuk zona waktu dan kasus reopen, clock SLA paused/breached serta tiket restricted. Rumus SLA perlu disetujui pemilik layanan sebelum digunakan sebagai laporan resmi.
- Uji kesehatan scheduler/worker sungguhan, event, job gagal, recovery dan deduplikasi pada lingkungan uji. Pemeriksaan sintetik yang mensimulasikan health bukan pengganti worker nyata.
- Latih skenario incident dan service request dari submit hingga close/reopen dengan peran nyata dan bukti UAT. Tentukan SLA, owner, tim, approver dan peserta melalui proses bisnis. Atur target/kalender dan scope pilot yang disetujui sebelum mengubah sakelar.

## Urutan rilis yang diusulkan

1. Susun paket/commit yang telah ditinjau, lakukan backup terbaru dan pastikan latihan di atas lulus.
2. Persiapkan maintenance window yang disetujui, lakukan migrasi dan inisialisasi sesuai hasil audit target. Pastikan pengajuan IT tetap tertutup. Jangan mengulang script lokal yang mengubah data production.
3. Deploy kode dan asset serta reload cache dan proses terkait sesuai mekanisme target. Verifikasi HTTP, migrasi, akses dan layanan existing. Jalankan pemeriksaan health worker/scheduler. Jika gagal, hentikan pembukaan dan ikuti rencana recovery.
4. Konfigurasi draft, tim, owner, approval, kalender, SLA dan identitas hasil asesmen. Uji preview dan UAT, kemudian publikasikan versi yang disetujui. Publikasi saja tidak membuka pengajuan.
5. Verifikasi `satset:it-pilot-readiness` dan `satset:it-health` pada lingkungan target. Setelah bukti bisnis, UAT, backup/restore dan dukungan disahkan, tetapkan peserta/katalog/version serta sakelar sesuai keputusan. Uji peserta, nonpeserta dan katalog di luar scope, kemudian monitor.

## Penghentian dan pemulihan

Untuk menghentikan pengajuan baru, set sakelar `SATSET_IT_REQUESTS_ENABLED=false` pada target dan pastikan cache serta semua proses relevan dimuat ulang. Uji web dan API agar benar-benar menolak pengajuan baru. Tiket existing dan data audit harus tetap dipertahankan.

Jangan menjalankan `migrate:rollback`, menjatuhkan tabel IT, atau me-restore database penuh hanya untuk mengembalikan kode. Setelah ada data/aktivitas IT, pengembalian kode dan pemulihan data memerlukan analisis kompatibilitas dan rekonsiliasi transaksi GA/IT sejak backup. Lakukan restore dan latihan pemulihan pada salinan terlebih dahulu. Catat owner, RPO/RTO dan langkah pemulihan yang sesuai topologi production.

## Status yang masih harus dibuktikan

- Belum ada akses untuk memeriksa target production atau staging, migration drift, konfigurasi dan worker yang efektif.
- Belum ada backup production terbaru dan bukti restore terisolasi untuk rilis ini.
- Cakupan commit dan pipeline deployment belum diverifikasi. Tidak ada push, publikasi konfigurasi, aktivasi feature flag, atau deployment yang dilakukan pada tahap review ini.
- Integrasi pencarian karyawan dari Portal, backlog historis, ekspor PDF/XLSX dan rincian SLA lanjutan belum selesai. Tidak boleh dipresentasikan sebagai fitur yang sudah tersedia.
