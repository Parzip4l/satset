# Penutupan Fase 0–1 IT Request Satset

Tanggal: 28 Oktober 2026. Acuan: [PLAN_IT_REQUEST_ITIL.md](PLAN_IT_REQUEST_ITIL.md).

Pembaruan: Fase 2 juga telah selesai. Laporan ini mempertahankan bukti penutupan Fase 0–1; status terbaru dan penambahan capability ID 31 tersedia di [IT_REQUEST_PHASE_2.md](IT_REQUEST_PHASE_2.md).

## Keputusan kesiapan

**Fase 0 dan Fase 1 selesai untuk melanjutkan pengembangan Fase 2.** Migration telah diterapkan pada MySQL Satset di Docker. Tidak ada migration aktif yang masih pending. Pengajuan IT baru tetap tertutup: fondasi ini belum merupakan rilis layanan IT untuk pengguna operasional.

| Gerbang fase | Hasil |
|---|---|
| Audit schema, data, route, integrasi dan hak akses | Selesai; baseline MySQL dan audit source tersedia |
| Backup lengkap database + file/konfigurasi | Selesai; disimpan privat dan diabaikan Git |
| Restore terisolasi | 44 tabel dan 7 file/konfigurasi cocok dengan backup |
| Pemetaan atau register pengecualian seluruh tiket | 15 dari 15 memiliki keputusan; tidak ada record baseline yang belum ditinjau |
| Rekonsiliasi migration lama | Migration starter duplikat diarsipkan; migration pendukung dan fondasi diterapkan |
| Verifikasi penerapan pada salinan MySQL | 22 pemeriksaan lulus sebelum sumber dimigrasikan |
| Verifikasi pada database sumber setelah penerapan | 24 pemeriksaan GET/policy lulus |
| Regresi otomatis aplikasi | 108 test, 529 assertions lulus |
| Integritas data lama | Proyeksi kolom asli pada 42 tabel identik dengan baseline; log migration dan sesi aktif dikecualikan |
| Bootstrap konfigurasi | ID 1 sesuai pilihan pengguna; manage dan publish, tanpa akses global/sensitif IT |
| Empat layanan dan isolasi akses | Tersedia; pengajuan IT tetap tertutup |

Kesiapan ini adalah penerimaan teknis Fase 0–1. Penetapan service owner bisnis, anggota tim operasional, approver katalog, target SLA dan UAT manusia tetap diperlukan sebelum publikasi layanan pada fase berikutnya. Nilai-nilai itu tidak dikarang untuk memenuhi checklist.

## Hasil review data

| Keputusan | Jumlah | Dasar |
|---|---:|---|
| Layanan IT | 10 | Kategori existing memiliki root berkode `IT` / IT Request |
| Konsumsi | 2 | Marker `payload.request_type=consumption` eksplisit |
| ATK/RTK | 1 | Marker `payload.request_type=atk_rtk` eksplisit, mengungguli kategori historis IT |
| Dipertahankan sebagai legacy | 2 | Kategori GA Request tanpa marker layanan yang cukup jelas |

Dua pengecualian adalah ID tiket **13 dan 14**. Keduanya tetap mempunyai domain kosong, tetap dapat dilacak menurut hak akses existing, dan memiliki keputusan `retained_legacy` beserta bukti/alasan di `service_domain_reviews`. Tidak dibuat layanan utama kelima dan tidak ditebak sebagai IT, Konsumsi, ATK/RTK atau Temuan.

Dari 10 tiket IT, **6** memiliki jenis existing yang eksplisit: 4 Incident dan 2 Service Request. Hanya enam ini yang dibuatkan `it_ticket_details`, dengan status yang dipetakan dari status existing. Empat lainnya masuk domain IT tetapi jenis catatannya tetap belum diketahui; tidak dipaksakan menjadi Incident. Semua berada dalam mode baca sampai workflow tahap berikutnya siap.

ID tiket, nomor, judul, isi payload, kategori lama, jenis tiket lama, status lama, tanggal, approval dan histori dipertahankan. Mapping hanya menambah domain dan metadata review/detail yang dapat diaudit. Query builder menghindari observer, approval baru dan broadcast notifikasi saat migrasi.

## Perubahan aplikasi dan database

- Empat `service_domains`: ATK/RTK, Konsumsi, Permintaan & Temuan, dan IT, terpisah dari jenis Incident/Service Request.
- Tambahan nullable `requests.service_domain_id`, detail IT, tim/keanggotaan, capability, dan register review.
- Empat kartu Request Center; Tiket Saya dan Persetujuan Saya menjadi navigasi terpisah.
- Halaman `/it`, detail IT, filter layanan pada daftar web/mobile, dan metadata domain pada bootstrap mobile.
- Endpoint lama menolak mutasi dan pemalsuan marker/domain IT. API v1 yang menerima email sebagai identitas tidak dapat membaca tiket IT.
- Pemohon/PIC/approver terkait dan anggota tim aktif mengikuti policy IT; role admin/GA/superadmin tidak otomatis dapat membaca IT. Tiket restricted memerlukan capability tambahan untuk pihak selain pemohon.
- Komentar/payload internal dan URL lampiran publik tidak dimasukkan dalam ringkasan mobile IT. Endpoint lampiran/history IT lama tetap tertutup.
- IT tidak dibroadcast ke daftar penerima GA/admin. Distribusi event ke tim IT diselesaikan pada fase workflow.
- Format ZIP backup lama dilindungi dari ekspor/clear/restore IT yang tidak lengkap. Backup database dan file lengkap tersedia sebagai jalur pemulihan fase ini.
- Relasi assignment diperbaiki ke `request_id`; typo fillable diperbaiki. Model dan seeder SLA/routing/escalation diselaraskan dengan schema actual. Seeder contoh SLA tidak dijalankan dan tidak menjadi kebijakan operasional.
- Tiga tim awal dibuat sebagai **draft/nonaktif**, tanpa anggota: IT Service Desk, IT Network & Infrastructure, IT Application Support. Tim teknis terpisah dari departemen organisasi.

## Rekonsiliasi migration

Starter `0001_01_01_000000_create_users_table.php` belum pernah dijalankan pada baseline, sementara migration users versi domain sudah dijalankan. File starter diarsipkan ke `database/legacy/` agar tidak membuat ulang tabel users/sessions/password reset. Tidak ada pencatatan migration fiktif atau penghapusan pengguna.

Sembilan migration diterapkan dan tercatat dalam batch 19:

1. Signal ID organisasi.
2. Token mobile Satset.
3. Portal sync dan audit approval.
4. Signature approval.
5. URL notifikasi menjadi text.
6. Submission token tiket.
7. Normalisasi status GA (tidak mengubah record baseline karena tidak ada kandidat).
8. Fondasi layanan IT.
9. Register review domain legacy.

Pendekatan diuji dahulu pada database hasil restore. Setelah sumber diterapkan, status migration diperiksa ulang dan semuanya `Ran`.

## Capability dan keputusan konfigurasi

Pengguna memilih **Muhamad Sobirin, ID 1** melalui konfirmasi pada chat. Dua capability diberikan melalui command operator dengan alasan yang dicatat pada log:

- `manage_it_configuration`
- `publish_it_configuration`

`view_it_queue` dan `view_restricted_it` **tidak** diberikan. Keanggotaan tim atau mandat bisnis service owner tidak ditetapkan dari nama role. Capability konfigurasi menyiapkan akses Fase 2; UI builder/publish belum dibuat.

Keputusan baseline untuk Fase 2:

| Area | Keputusan |
|---|---|
| Pemisahan layanan | Empat domain stabil |
| Pemohon | Tetap identitas login/SSO; data legacy tidak ditulis ulang |
| Pengelola konfigurasi | ID 1; publikasi terpisah dari hak baca isi tiket |
| Tim | Tiga draft nonaktif; anggota dan mandat diisi sebelum routing operasional |
| Jenis IT baru | Incident dan Service Request; Problem/Change adalah catatan lanjutan |
| SLA, approval, jam dukungan | Belum diaktifkan; wajib ditetapkan pemilik layanan sebelum bundle dipublikasikan/rilis |
| Data legacy ambigu | Retained dalam register pengecualian, tidak menghalangi pembangunan builder |
| Pengajuan IT | Tertutup sampai gerbang workflow, akses, storage privat, SLA dan pilot terpenuhi |

## Backup dan bukti pemulihan

Backup privat berada pada:

`storage/app/private/it-phase01/baseline-20261028T063004Z/`

Folder mode 0700, file sensitif mode 0600, dan tidak masuk Git. Isi utamanya:

- `database.sql.gz`: dump database lengkap termasuk schema, data, routines, triggers dan events.
- `files-and-config.tar.gz`: file aplikasi yang tersimpan, `.env`, dan konfigurasi cache; tidak diletakkan pada disk publik.
- `baseline.json`: schema/count/hash dan metadata klasifikasi untuk operator.
- `file-manifest.json`: SHA-256 file.
- `restore-verification.json`: hasil restore 44 tabel / 7 file.
- `mysql-verification.json`: hasil pemeriksaan salinan MySQL sebelum deployment.
- `source-deployment.json`: hasil penerapan dan pemeriksaan integritas sumber.

Database verifikasi `satset_it_verify_20261028064241` dipertahankan untuk pemeriksaan lanjutan. Restore file memakai direktori terisolasi dan tidak menimpa file aplikasi asal. Jangan menyalin backup sensitif ke repo, disk publik atau laporan umum.

Saat cutover, pembaruan sesi login terdeteksi oleh guard baseline. Penerapan pertama dihentikan sebelum menulis. Setelah perbedaan dikonfirmasi hanya pada `sessions`, guard memperbolehkan perubahan sesi aktif dan tetap mensyaratkan semua data bisnis identik. Hasil akhir membuktikan nilai kolom asli pada 42 tabel tetap cocok; perubahan migration registry dan sesi dicatat sebagai pengecualian yang memang diharapkan.

## Verifikasi

Pemeriksaan MySQL mencakup:

- Jumlah tiket dan register review; 2 pengecualian dan 6 detail bertipe eksplisit.
- Draft tim tanpa anggota yang belum ditunjuk.
- Kolom/tabel migration pendukung tersedia.
- Capability konfigurasi ID 1 terpisah dari akses global/sensitif.
- HTTP 200 untuk Request Center, Tiket Saya, Persetujuan, Daftar GA, form Konsumsi, ATK/RTK, Permintaan & Temuan, gudang, dashboard GA, dan halaman IT.
- Pemohon tiket IT lama tetap dapat membaca tiketnya.
- Admin lain tidak otomatis memiliki akses; HTTP detail IT menghasilkan 403 pada pengujian sumber.
- Pengajuan IT tetap tertutup.
- Nginx aktual mengarahkan tamu dari `/it` ke login (302).

Suite lokal **108 test / 529 assertions lulus**, termasuk regresi konsumsi, gudang/serah terima, GA follow-up, portal approval, notifikasi, mobile SSO, backup, akses IT, dan review legacy. Smoke test root lama diperbaiki agar menguji redirect dashboard yang memang menjadi kontrak route, sehingga tidak menyisakan kegagalan palsu 200-versus-302.

Pengujian otomatis dan smoke terautentikasi pada MySQL tidak menggantikan UAT manusia atau uji integrasi eksternal nyata saat rilis IT. Selama pengujian smoke, panggilan eksternal dinonaktifkan/dicegah agar tidak mengirim notifikasi atau keputusan approval nyata.

## Runbook operator

Audit dan status (read-only):

```sh
docker compose exec -T app php artisan satset:it-audit --json
docker compose exec -T app php artisan migrate:status
```

Review legacy bersifat dry-run secara default dan idempotent setelah diterapkan:

```sh
docker compose exec -T app php artisan satset:review-legacy-domains --reviewer=1
```

`--apply` diperlukan untuk menulis keputusan baru. Command memeriksa ancestry kategori dan konflik domain dalam transaksi, tanpa menebak dari judul tiket.

Script operator yang disediakan:

- `scripts/it_phase01_ops.py`: backup, restore terisolasi, verifikasi migration, pemeriksaan drift, dan penerapan yang mensyaratkan bukti verifikasi.
- `scripts/it_phase01_snapshot.php`: proyeksi hash tabel tanpa mengekspor isi data sebagai output umum; mode inspect khusus operator dapat menampilkan metadata review.
- `scripts/it_phase01_verify.php`: GET smoke/policy untuk database salinan; sumber hanya dengan opsi eksplisit `--source-read-only`.

Script cutover merupakan alat untuk baseline ini, bukan automation migration umum. Pengulangan `apply-source` pada database yang sudah dimigrasikan akan ditolak guard baseline. Untuk perubahan berikutnya buat backup baru dan gunakan migration/plan fase terkait.

## Batas rollback dan langkah berikutnya

Utamakan rollback kode yang kompatibel dengan schema additive. Jangan menjalankan `migrate:rollback` seluruh batch 19 pada database berisi mapping IT: batch juga memuat migration pendukung lama. `down()` fondasi diuji di SQLite, tetapi rollback data actual harus memakai prosedur pemulihan lengkap yang sudah diuji.

**Lanjut Fase 2:** katalog layanan IT, scope kategori, form builder dengan validator server, bundle konfigurasi berversi, preview, simulasi, serta publish/rollback. Belum membuka pengajuan atau penanganan IT pada fase ini. Empat tiket IT legacy tanpa jenis tetap mempertahankan metadata aslinya; penetapan jenis operasional selanjutnya harus eksplisit dan tercatat.
