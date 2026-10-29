# IT Request — Persiapan pilot Fase 5

Status: **gerbang pilot dan UAT teknis terisolasi siap; pilot pengguna belum berjalan**. Pengajuan IT masih tertutup. Cakupan, personel dan SLA aktual memerlukan pencatatan keputusan dan UAT oleh pengguna yang ditunjuk; hasil pengujian sintetis bukan persetujuan bisnis atau UAT pengguna.

> Arahan perencanaan 29 Oktober 2026: untuk melanjutkan diskusi dan pengembangan fitur, kesiapan pilot diasumsikan selesai atas arahan pengguna. Ini adalah asumsi kerja, bukan bukti bahwa UAT pengguna, persetujuan bisnis, konfigurasi aktif, atau pembukaan pilot telah dilakukan. Kondisi dan hasil pemeriksaan teknis tetap ditampilkan sesuai keadaan sebenarnya.

## Rancangan pilot awal

Atas arahan untuk memilih cakupan, draft **v4** pada Docker local sudah disiapkan dari draft v3 untuk dua item katalog berikut:

- `network_issue` — gangguan jaringan/Wi-Fi (Incident).
- `access_request` — permintaan akun/hak akses (Service Request, approval sesuai aturan).

Peserta pemohon awal dapat menggunakan Muhamad Sobirin ID 31. ID 1 dan 31 tetap memiliki capability pengaturan/publikasi yang sudah disetujui; tidak ada keanggotaan tim atau hak baca tiket IT sensitif yang ditambahkan secara otomatis. Tim service desk, koordinator, teknisi, approver, pemilik layanan dan pengganti saat tidak tersedia harus ditetapkan dari pengguna nyata sebelum aktivasi.

Usulan awal target dalam **menit layanan**, untuk dibahas sebagai bahan UAT, belum merupakan SLA yang disetujui:

| Prioritas | Respons manusia | Pemulihan jaringan / pemenuhan akun |
| --- | ---: | ---: |
| P1 | 15 | 60 |
| P2 | 60 | 240 |
| P3 | 240 | 1.440 |
| P4 | 480 | 2.880 |

Draft v4 berisi angka usulan pada tabel ini, aturan approval akun melalui Portal, serta peringatan/eskalasi ke koordinator. Draft ini masih belum lengkap untuk publikasi karena owner dan tim belum ditetapkan.

Contoh kalender awal: Senin–Jumat, 08.00–17.00 Asia/Jakarta, dengan daftar hari libur yang disepakati. Pastikan kebutuhan P1 di luar jam kerja; bila butuh 24×7 gunakan kalender dan personel on-call yang memang tersedia. Menunggu pemohon dapat dijeda jika disetujui; menunggu vendor atau pekerjaan internal tetap berjalan sebagai default. Owner harus menetapkan penerima eskalasi dan menguji angka-angka di atas sebelum dipublikasikan.

## Gerbang pembukaan

`PilotGate` memerlukan seluruh syarat berikut untuk menampilkan atau menerima pengajuan:

1. Feature flag `SATSET_IT_REQUESTS_ENABLED=true`, serta peserta dan katalog pada daftar pilot eksplisit. Peserta di luar daftar dan katalog di luar cakupan ditolak pada form maupun submit web/mobile.
2. Konfigurasi IT aktif yang **published schema 3** cocok dengan `SATSET_IT_PILOT_VERSION_ID`, checksum valid, dan masih lulus validasi owner/tim/routing/SLA. Pilot harus mencakup sekurangnya satu Incident dan satu Service Request. Versi aktif berubah atau rollback ke schema lama menyebabkan pengajuan baru tertutup.
3. Tim tujuan/fallback yang relevan memiliki koordinator; tiket rahasia memerlukan koordinator yang memiliki hak baca rahasia. Resolver Portal yang dipilih harus dikonfigurasi; approver tetap tidak boleh menjadi peserta pemohon untuk item yang sama.
4. Persetujuan cakupan/tim/target, UAT pengguna, uji restore cutover, dan kesiapan support/rollback telah dicatat dan disahkan. Flag ini merupakan **pernyataan operator**; simpan bukti yang sesuai di lokasi organisasi, jangan mengubahnya menjadi true berdasarkan simulasi.
5. Scheduler dan worker sehat serta tidak ada pengiriman failed/stale. Jika kesehatan tidak tersedia atau memburuk, pengajuan baru tertutup sampai pulih.

Gerbang dapat diperiksa tanpa mutasi melalui `php artisan satset:it-pilot-readiness` atau Konfigurasi IT → **Kesiapan pilot**. Hak konfigurasi dapat melihat hasil readiness tanpa memperoleh isi tiket. Terdapat batas 100 peserta dan 20 item katalog untuk membatasi cakupan awal.

Parameter ada pada `.env.example` dengan nilai default tertutup. Nilai operator harus dicocokkan dengan bukti persetujuan/UAT/cutover yang sebenarnya. Dalam instalasi dengan Laravel config cache atau proses FPM berumur panjang, perubahan `.env` tidak langsung terbaca: ikuti prosedur pembaruan cache konfigurasi dan reload semua proses aplikasi/worker/scheduler. Verifikasi dengan readiness dan satu akun di luar daftar. Jangan menganggap flag sudah tertutup hanya karena file `.env` sudah diubah.

Penutupan darurat: set `SATSET_IT_REQUESTS_ENABLED=false`, refresh konfigurasi/reload proses, lalu uji submit web dan mobile menghasilkan 423. Tiket yang sudah masuk tetap dapat dibaca dan ditangani sesuai otorisasi. Jangan menghapus konfigurasi, ticket, clock atau outbox untuk menutup pilot.

## Checklist UAT dan pelatihan

| Peran | Skenario yang wajib dikerjakan dan dicatat |
| --- | --- |
| Superadmin | Tetapkan tim/koordinator, owner, fallback, kategori dan kalender; pratinjau deadline; publikasikan schema 3 dan catat checksum. |
| Pemohon pilot | Ajukan Wi-Fi dan akun; lihat status/lampiran; terima resolusi, konfirmasi atau reopen; coba pengguna/katalog di luar cakupan. |
| Service Desk | Triage, impact/urgency, claim, penugasan, transfer dan penanganan exception; periksa deadline serta alasan pending. |
| Teknisi | Respons manusia, catatan internal, tugas dan lampiran privat, pause/resume yang sesuai, resolusi dan riwayat. |
| Approver | Terima permintaan akun, approve/reject/revision; cek self-approval dan keputusan dua kali ditolak. |
| Operator | Hentikan/pulihkan scheduler dan worker di lingkungan percobaan; verifikasi backlog, breach, retry dan tidak ada notifikasi duplikat. |
| Penanggung jawab | Cocokkan target, jumlah tiket dan referensi setelah uji cutover; tetapkan dukungan, batas pilot, kontak eskalasi, keputusan go/no-go, dan rencana rollback. |

Untuk setiap skenario catat tanggal, pemeran, lingkungan, versi bundle/checksum, data uji, hasil yang diharapkan/aktual, bukti yang aman dan keputusan lulus/gagal. Simpan bukti hak akses dan data sensitif di tempat privat, bukan pada repositori. Temuan gagal harus diselesaikan atau diputuskan secara eksplisit sebelum go-live.

## Rekonsiliasi dan cutover

Backup database dan file privat, lalu lakukan restore pada database terpisah dan cocokkan tabel, jumlah, hash file dan referensi; verifikasi lagi jika ada perubahan antara backup dan cutover. Data IT lama tetap mode baca kecuali ada aturan adopsi dan cutover yang disepakati; SLA historis yang tidak punya event lengkap tidak boleh dipalsukan. Catat nomor/ID, requester, status, approval dan lampiran sebelum/sesudah, serta tangani delta transaksi pada masa cutover. Ekspor ZIP legacy bukan pengganti backup lengkap untuk tiket IT.

Latih peran di atas menggunakan data yang tidak sensitif. Setelah UAT dan keputusan go/no-go, publikasikan konfigurasi, tetapkan daftar peserta/katalog/versi dan pernyataan persetujuan, aktifkan feature flag hanya untuk pilot, lalu pantau backlog, breach, kesehatan worker, notifikasi dan hak akses. Revalidasi UAT serta cakupan ketika versi pilot berubah; gerbang versi otomatis menghentikan pengajuan baru sampai versi baru disetujui.

## Hasil yang sudah diverifikasi

- UAT **sintetis**, dengan akun dan tim sementara pada database `satset_it_verify_20261028100455`: sembilan pemeriksaan lulus untuk dua katalog sampai closed dengan SLA, pembatasan peserta/katalog, sign-off UAT, perubahan versi, kill switch dan akses tiket existing. Health worker dalam UAT ini disimulasikan; verifikasi worker sungguhan sudah dilakukan pada Fase 4.
- Fixture dihapus dan hash seluruh tabel asal pada database verifikasi tetap sama, kecuali sesi yang memang dikecualikan. Bukti privat: `storage/app/private/it-phase01/baseline-20261028T100227Z/phase05-isolated-uat-final.json`.
- Suite aplikasi dan regresi: **181 test / 861 assertions lulus**. Tidak ada peserta/tim nyata, nilai SLA bisnis, atau sign-off yang diset otomatis.

## Yang masih diperlukan untuk menyelesaikan Fase 5

UAT oleh pengguna nyata, penetapan tim/approver dan SLA final, uji restore terbaru untuk cutover, latihan peran, keputusan go/no-go, pembukaan terbatas dan evaluasi pilot masih harus dilakukan. Fase 5 tidak dapat dinyatakan selesai hanya dari UAT sintetis.
