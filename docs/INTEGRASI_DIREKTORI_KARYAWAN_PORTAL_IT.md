# Integrasi direktori karyawan Portal untuk konfigurasi IT

## Kondisi saat ini

Pilihan pengguna pada editor konfigurasi IT dan halaman tim berasal dari tabel `users` Satset, sehingga bukan direktori lengkap Portal. Login SSO mobile dan resolver approval Portal dapat membuat atau menemukan akun lokal berdasarkan email, tetapi hal tersebut tidak menyinkronkan seluruh karyawan. Resolver approval yang sudah ada menentukan approver untuk suatu tiket dan bukan API pencarian seluruh karyawan. Satset menyimpan referensi pengguna lokal dalam kepemilikan layanan, tim, approval, SLA dan audit, sehingga ID Portal tidak dapat langsung dianggap sama dengan ID Satset.

## Mekanisme Portal yang ditemukan

- Web Portal `/coworkers` mencari nama, email, nomor pegawai, jabatan dan departemen dari tabel pengguna Portal, dengan pagination. Endpoint ini mengembalikan halaman web dan memakai sesi Portal, bukan kontrak antarsistem Satset.
- API Portal `GET /api/mobile/v1/chat/contacts?search=...` mencari pengguna Portal berstatus aktif berdasarkan nama, email, jabatan atau departemen. API ini mengembalikan ID Portal, nama, email dan atribut kontak, dibatasi 50 hasil, serta mengecualikan pengguna yang sedang login. API ini memerlukan bearer token mobile Portal milik pengguna. Mekanisme ini dapat dijadikan referensi query direktori, tetapi tidak dapat langsung dipakai oleh backend Satset dengan shared secret Satset atau dianggap sebagai daftar lengkap/paginated.
- Direktori pengguna Portal dapat diisi lewat sinkronisasi Microsoft Graph dan autentikasi LDAP. Ketersediaan dan kesegaran data bergantung pada proses tersebut, sehingga daftar pengguna Portal tidak otomatis identik dengan seluruh karyawan organisasi.
- Satset SSO menerima data satu pengguna dari Portal melalui pertukaran bertanda tangan dan membuat atau memperbarui akun lokal berdasarkan email. Token hasil pertukaran Satset bukan bearer token untuk API mobile Portal. Approval resolver memakai HMAC antarsistem yang sudah ada untuk menentukan approver tiket, bukan untuk pencarian daftar.

Kesimpulan: gunakan kembali query pengguna aktif seperti `ChatController::contacts` atau `CoworkerController`, serta pola autentikasi HMAC seperti approval resolver, untuk menyiapkan endpoint direktori antarsistem di Portal. Akses perlu dibatasi dan perilaku pagination/status/identitas perlu ditetapkan. Tidak perlu membuat integrasi Microsoft Graph kedua di Satset. Implementasi endpoint Portal berada di proyek `intranet`, di luar workspace Satset saat ini.

## Tujuan dan kebutuhan integrasi

- Portal menjadi sumber pencarian karyawan aktif untuk pemilik layanan, anggota tim, approver tertentu dan penerima eskalasi.
- Sediakan endpoint direktori yang disetujui pemilik Portal dengan autentikasi antarsistem, pencarian nama/email, pagination, pembatasan hasil dan hanya atribut yang diperlukan: ID Portal stabil, nama, email kerja dan status aktif. Kontrak, izin pemanggilan, audit dan batas penggunaan perlu dikonfirmasi. Jangan mengasumsikan endpoint resolver approval mendukung pencarian karyawan.
- Satset memetakan ID Portal stabil ke identitas lokal yang unik. Lakukan pencocokan email hanya saat identitas terverifikasi dan tidak ambigu. Duplikasi, email berubah, akun nonaktif dan konflik identitas masuk antrean peninjauan, bukan digabung otomatis.
- Bila karyawan belum memiliki akun lokal, buat identitas lokal minimum hanya melalui mekanisme provisioning yang disepakati dan aman. Pemilihan karyawan tidak otomatis memberikan hak tiket, akses rahasia atau kemampuan konfigurasi. Pemberian peran tetap lewat alur yang berwenang.
- Sumber Portal tidak tersedia harus menghasilkan pesan yang jelas, bukan daftar lokal yang disajikan sebagai direktori Portal. Pertahankan tampilan nama anggota yang sudah tersimpan, tetapi jangan membiarkan data usang dianggap konfirmasi status aktif. Tentukan kebijakan cache, waktu sinkronisasi, pencabutan akses dan penanganan downtime bersama pemilik layanan.
- Gunakan kembali resolver Portal untuk atasan ketika proses approval memang membutuhkan atasan pemohon. Direktori karyawan terpisah dari resolver approval dan tidak boleh menebak reporting line.

## Implementasi setelah kontrak tersedia

1. Konfirmasi endpoint, skema, autentikasi, data yang boleh diambil dan perilaku untuk pengguna nonaktif.
2. Buat klien Portal pada backend Satset, dengan batas waktu, validasi respons, pagination dan penanganan kegagalan. Jangan meneruskan rahasia Portal ke browser.
3. Tambahkan pemetaan identitas yang berversi dan terlindungi, serta proses provisioning/konflik yang diaudit. Tinjau migrasi dan backup sebelum penerapan.
4. Ganti pilihan pengguna pada Tim IT dan editor konfigurasi menjadi pencarian server-side yang terhubung ke Portal. Pertahankan referensi lokal yang diperlukan oleh workflow dan validasi server setiap perubahan.
5. Uji identitas yang sudah ada/belum ada, nama sama, email duplikat, perubahan email, karyawan nonaktif, Portal timeout, pagination, izin per peran, pencegahan self-approval dan akses tiket rahasia. Pastikan tidak ada perubahan terhadap anggota atau hak akses tanpa tindakan yang diotorisasi.
6. UAT pada data dan lingkungan yang disepakati, lalu rencanakan penerapan Portal dan Satset serta pemantauan.

Status: menunggu informasi atau rancangan endpoint direktori Portal. Belum ada perubahan integrasi atau pemberian akses baru.
