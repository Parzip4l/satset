# IT Request — Fase 6, progres fondasi praktik IT

Tanggal: 28 Oktober 2026. Status: **berjalan; belum selesai**. Fase 5 masih menunggu UAT pengguna, penetapan tim/SLA, dan pilot. Pengajuan IT tetap tertutup.

## Bagian yang telah dibangun

- Catatan Problem, Change dan Knowledge yang terpisah dari empat layanan utama. Setiap catatan memiliki owner, tim, klasifikasi rahasia, status, nomor, versi untuk mencegah overwrite, dan audit alasan perubahan.
- Problem dapat ditautkan ke Incident yang dapat dibaca, mencatat RCA, workaround dan pencegahan. Known error mensyaratkan workaround; review/close mensyaratkan RCA dan pencegahan. Penyelesaian Incident tidak menunggu Problem.
- Change dapat ditautkan ke Problem, mencatat jenis standard/normal/emergency, risiko, rencana implementasi, pengujian, rollback, jadwal dan authority. Otorisasi memerlukan authority yang berbeda dari owner dengan capability khusus. Rencana terkunci setelah otorisasi; hasil gagal/rollback dan review dicatat.
- Register awal aset/CI tersedia untuk tim IT: kode aset, jenis CI, lokasi, serial/pemasok dan tautan ke layanan IT aktif. Problem dan Change dapat dihubungkan ke aset/CI yang dapat diakses. Saat ini register masih terbatas untuk petugas, belum terhubung langsung ke form pemohon atau pencocokan aset otomatis.
- Knowledge dapat ditautkan ke Change yang sudah ditutup dengan review. Artikel memerlukan isi dan tanggal review, dan publikasi memerlukan reviewer terpisah dengan capability khusus. Artikel saat ini hanya terlihat bagi anggota tim yang berhak; publikasi belum berarti penyebaran ke seluruh pengguna.
- Web `/it/practices` dan API mobile `/api/mobile/v1/satset/it/practices` menggunakan layanan domain yang sama. Hubungan hanya ditampilkan bila pengguna berhak melihat kedua sisi. Pencatatan dari tiket/Problem rahasia tidak boleh mengarah ke catatan tidak rahasia.
- Capability baru `manage_it_practices`, `authorize_it_change`, `publish_it_knowledge` tersedia terpisah. **Belum ada pemberian capability tersebut**; akun ID 1/31 tetap hak konfigurasi yang telah disepakati. Selain capability, anggota tim aktif dan hak baca rahasia tetap diperlukan.

## Verifikasi fondasi

- Suite lokal: 188 test / 929 assertions lulus, termasuk jalur Incident → Problem → Change → artikel Knowledge, workaround, pemisahan authority, rollback, konflik versi dan isolasi akses.
- Uji restore backup privat `storage/app/private/it-phase01/baseline-20261028T103219Z/` cocok untuk 71 tabel dan 7 file, pada `satset_it_verify_20261028103231`.
- Migrasi dan 14 pemeriksaan MySQL pada restore lulus; akun/tiket/catatan sintetis dihapus dan data existing diverifikasi. Kesehatan worker dalam uji tersebut disimulasikan; pemeriksaan runtime sebenarnya tersedia dari Fase 4.
- Migrasi `2026_10_28_000006_create_it_practice_foundation` diterapkan pada MySQL Docker local. Setelah penerapan, 67 tabel existing cocok dengan backup; `sessions`, `it_queue_jobs`, `it_worker_heartbeats` dikecualikan karena aktif berubah, dan registry migrasi memang bertambah. Bukti privat: `phase06-practices-assets-verification.json` dan `phase06-practices-deployment.json` dalam folder backup.

## Pekerjaan Fase 6 yang masih terbuka

1. Lengkapi hubungan aset/CI langsung ke tiket, akses aset bagi pemohon sesuai hak, histori perubahan isi sebelum/sesudah, dan pengujian simultan pembuatan kode aset pada MySQL.
2. CSAT satu respons per siklus dan improvement records dengan owner/tindak lanjut.
3. Vendor dan OLA: kewenangan, komitmen, target internal, serta PIC internal; jangan menyatakan SLA vendor sudah dihitung.
4. Integrasi monitoring yang diautentikasi, deduplikasi alert, aturan pembuatan Incident, pengendalian banjir tiket, dan uji integrasi pada lingkungan terpisah.
5. Pengujian pengguna nyata dan keputusan peran/authority, termasuk model pre-authorized standard Change dan jalur emergency yang sesuai kebijakan organisasi. Implementasi awal saat ini tetap memerlukan authority terpisah untuk semua jenis Change.
6. Validasi UI, skenario pemulihan dan laporan menyeluruh setelah modul-modul tersebut terintegrasi. Mekanisme revisi Change terotorisasi, artikel untuk pemohon, masa review knowledge otomatis, dan pengujian concurrency MySQL khusus hubungan juga masih perlu dirancang.

Jangan menandai Fase 6 selesai atau membuka layanan berdasarkan fondasi ini saja. Bukti UAT sintetis bukan pengganti UAT pengguna dan penerapan local bukan rilis production.
