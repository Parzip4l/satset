# Penutupan Fase 2 — Konfigurasi dan Form Dinamis IT

Tanggal: 28 Oktober 2026. Acuan: [PLAN_IT_REQUEST_ITIL.md](PLAN_IT_REQUEST_ITIL.md).

## Status

**Fase 2 selesai dan diterapkan pada MySQL Satset local di Docker.** Pengelola dapat mengatur layanan, kategori/subkategori, katalog, lokasi, template dan field melalui UI, mencoba tiga peran, menyimpan draft, mempublikasikan versi dan melakukan rollback konfigurasi.

Akses: `/it/configuration`, atau sidebar **Layanan IT → Konfigurasi IT**.

Pengajuan IT baru tetap tertutup. Publikasi Fase 2 hanya mengaktifkan definisi katalog/form; workflow, routing, approval dan SLA operasional menunggu Fase 3–4. Pada database sumber **belum ada versi terpublikasi atau aktif**.

## Hasil penerapan dan akses

| Area | Hasil |
|---|---|
| Migration | `2026_10_28_000003_create_it_configuration_versions` sudah diterapkan |
| Penyimpanan | Set konfigurasi, versi bundle, audit event, dan referensi versi/jawaban pada detail tiket |
| Draft v1 | Baseline: 3 layanan IT, 7 kategori, 7 item katalog, 2 template |
| Draft v2 | Dibuat melalui UI; subkategori Wi-Fi di bawah Jaringan serta field Lokasi kejadian ditambahkan dan tersimpan |
| Versi aktif | Belum ada; tidak ada publikasi otomatis di database sumber |
| Pengelola | ID 1 dan ID 31 memiliki manage/publish; penambahan ID 31 dikonfirmasi pengguna pada chat |
| Batas akses | Keduanya tidak mendapat `view_it_queue` atau `view_restricted_it` |
| Regresi otomatis | 127 test, 613 assertions lulus |
| Verifikasi MySQL terisolasi | 22 pemeriksaan edit/preview/publish/rollback/integritas lulus |
| Uji browser local | Halaman editor, tambah kategori/field, simpan draft, preview peran, serta pesan validasi berhasil |

Seeder idempotent hanya membuat draft awal bila set IT belum ada. Data/kategori GA existing tidak diganti. Master IT disimpan dalam bundle versi yang terpisah dari `problem_categories` legacy sehingga satu publikasi selalu merujuk kumpulan referensi yang konsisten.

## Editor superadmin

Tab yang tersedia:

- **Layanan:** kode stabil, nama, deskripsi, service owner, tim fallback, status aktif.
- **Kategori:** kode, nama, parent/subkategori, urutan, status aktif.
- **Katalog:** nama/deskripsi, layanan, kategori, jenis Incident/Service Request, template form dan status aktif.
- **Form builder:** pilih template, tambah/urutkan field, buka detail field satu per satu, atur karakteristik dan kondisi.
- **Lokasi:** master lokasi bertingkat dan status aktif, sebagai sumber pilihan form.
- **Preview & uji:** pilih template serta peran Pemohon/Teknisi/Approver; uji jawaban memakai validator server tanpa membuat tiket.

Kode yang sudah dipublikasikan menjadi identitas historis. Menghapus item dari draft tidak menghapus item dari snapshot lama; referensi yang masih dipakai harus diperbaiki atau dinonaktifkan sebelum penyimpanan/publikasi lolos.

Field mendukung 13 tipe: text, textarea, integer, decimal, date, datetime, time, select, multiselect, radio, checkbox, file, dan reference.

Pengaturan field meliputi kode, label, bantuan, placeholder, section, lebar, required, default, batas angka/panjang, pilihan berkode, peran yang dapat melihat, peran read-only, serta penanda registrasi untuk laporan. Penanda laporan belum membuat dashboard/index SQL otomatis; pemakaiannya dilanjutkan bersama fitur pelaporan.

Referensi master dibatasi ke layanan IT, kategori IT, lokasi dalam bundle, dan tim aktif. Referensi tim internal tidak boleh tampil kepada Pemohon. Tidak ada URL/query/script bebas sebagai sumber pilihan.

## Kondisi, validasi, dan file

- Operator kondisi: `equals`, `not_equals`, `in`, `is_empty`, dengan grup AND/OR bertingkat.
- Kedalaman/node kondisi dibatasi; parent kategori/lokasi dan dependensi field tidak boleh membentuk siklus.
- Kondisi harus merujuk field serta nilai pilihan/master yang sah. Field publik tidak boleh bergantung pada field internal yang tersembunyi dari peran tersebut.
- Field tersembunyi, read-only dan tidak dikenal yang dipaksakan lewat request ditolak oleh server.
- Default read-only ditetapkan server. Field wajib yang read-only harus memiliki default valid.
- Jenis, batas, pilihan, duplikasi pilihan, tanggal/waktu, angka bulat/desimal, format email/URL/telepon dan referensi diperiksa di server.
- Nilai integer pecahan atau overflow tidak diam-diam diubah menjadi angka lain.
- Attribute identitas, penugasan, status, domain, nomor tiket, token dan beberapa nama credential dilindungi oleh registry kode sistem.
- Definisi memakai operator dan format yang disediakan aplikasi; tidak mengeksekusi PHP, JavaScript, SQL atau ekspresi pengguna.
- File dibatasi maksimal 5 per field dan 5120 KB per file. Ekstensi/MIME berada dalam allowlist platform; konfigurasi tidak dapat memperluasnya ke executable.
- Preview menerima multipart file untuk pengujian MIME/ukuran/jumlah, tetapi tidak memindahkannya ke storage publik, tidak membuat attachment dan tidak menyimpan jawaban sebagai tiket.
- Label/help/options ditampilkan dengan escaping Blade atau DOM `textContent`; JSON untuk editor disematkan secara inert dengan HEX escaping.

Batas bundle: 256 KB, maksimal 100 template dan 60 field per template. Batas daftar master dan pilihan juga diperiksa sebelum diterima.

Validator form yang sama digunakan oleh preview web dan API mobile; tidak ada validator browser yang dianggap sebagai sumber otorisasi.

## Versi, publikasi, dan rollback

Satu bundle memuat `services`, `categories`, `catalog`, `locations`, `forms`, dan profil proses yang masih dikunci sebagai pending Fase 3–4.

1. Buat draft dari versi sumber/aktif; sumber tidak diubah.
2. Edit draft dan isi alasan perubahan.
3. Simpan dengan `lock_version`; request kedaluwarsa menghasilkan 409 dan tidak menimpa pekerjaan lain.
4. Preview menggunakan versi terakhir yang tersimpan. Pergantian template/peran preview tidak menandai draft sebagai berubah.
5. Publikasi memerlukan capability terpisah, alasan, lock draft dan revision set yang masih sesuai.
6. Server memvalidasi bundle, referensi, kompatibilitas field dan checksum, lalu mengganti pointer aktif dalam transaksi.
7. Snapshot published tidak dapat diedit/dihapus melalui model/endpoint. Tipe field berkode sama tidak dapat diganti setelah dipublikasikan; gunakan key baru.
8. Rollback hanya boleh menuju snapshot yang pernah published. Pointer aktif berubah; snapshot dan tiket tidak ditulis ulang.

Checksum memakai bentuk JSON kanonis agar urutan object dari MySQL tidak menghasilkan perbedaan palsu. Lock set melindungi nomor versi serta pointer aktif dari publikasi bersamaan.

Audit mencatat aktor, alasan, aksi, checksum sebelum/sesudah dan metadata versi. Penyimpanan draft juga merekam snapshot konfigurasi sebelum/sesudah untuk penelusuran perubahan berikutnya. Jawaban/file preview tidak dimasukkan ke audit.

Kolom nullable `configuration_version_id`, `form_key`, dan `form_answers` pada detail IT menyiapkan pengikatan tiket ke snapshot pada Fase 3. Tiket legacy tetap tidak dipaksakan memiliki schema/jawaban baru. Pengujian memastikan jawaban yang diikat ke versi lama tetap memakai label/schema awal setelah publikasi berikutnya dan rollback.

## Route dan capability

Web, dengan autentikasi sesi dan CSRF:

| Method | Path | Hak |
|---|---|---|
| GET | `/it/configuration` | manage atau publish |
| GET | `/it/configuration/versions/{version}` | manage atau publish |
| POST | `/it/configuration/drafts` | manage |
| PUT | `/it/configuration/versions/{version}` | manage; hanya draft |
| POST | `/it/configuration/versions/{version}/publish` | publish |
| POST | `/it/configuration/versions/{version}/rollback` | publish |
| POST | `/it/configuration/versions/{version}/preview` | manage atau publish |

API preview administrative: `POST /api/mobile/v1/satset/it/configuration/versions/{version}/preview`, memakai bearer token dan capability yang sama. Parameter peran hanya untuk simulasi administratif; bukan mekanisme pemberian hak kepada pemohon.

Body preview memakai `form_key`, `role`, `answers`, dan `validate`. Upload dikirim sebagai `uploads[field_key][]`. Response memuat proyeksi field sesuai peran, errors/valid, `persisted=false`, dan penanda pengajuan masih tertutup. Status 422 untuk input yang tidak sah sama pada web dan API.

## Verifikasi dan bukti penerapan

Sebelum migration dibuat backup baru di folder privat:

`storage/app/private/it-phase01/baseline-20261028T082137Z/`

Backup/restore membandingkan **52 tabel dan 7 file/konfigurasi**. Salinan verifikasi: `satset_it_verify_20261028082459`.

Di salinan MySQL, pengujian menambah kategori/field, menyimpan draft, menolak save kedaluwarsa, mempreview tiga peran, menolak input tersembunyi, mempublikasikan v1 dan v2, rollback ke v1, menolak perubahan snapshot published, serta menolak siklus kategori. **22 pemeriksaan lulus.** Tidak ada jumlah tiket atau binding tiket legacy yang berubah.

Migration dan seeder sumber kemudian diterapkan setelah verifikasi lolos. Proyeksi nilai kolom lama pada **50 tabel** cocok dengan baseline, selain migration registry dan sesi aktif yang memang berubah. Sesudah pemeriksaan ini, dua capability konfigurasi untuk ID 31 ditambahkan secara eksplisit sesuai izin pengguna; perubahan tersebut terpisah dan tercatat pada log operator.

Melalui Chrome local dengan akun ID 31:

- Membuka halaman Konfigurasi IT serta editor.
- Membuat draft v2 dari baseline v1.
- Menambah `wifi` sebagai subkategori `network` dan menyimpannya.
- Menambah field `incident_location` / Lokasi kejadian pada template Incident, batas 150 karakter, kemudian menyimpannya.
- Memastikan field muncul pada preview yang tersimpan.
- Memastikan catatan internal tidak tampil pada Pemohon dan tampil pada Teknisi.
- Memastikan validasi server menunjukkan pesan wajib diisi dalam bahasa Indonesia.

Pemeriksaan sumber mengonfirmasi dua draft, nol published, pointer aktif kosong, hasil edit UI tersimpan, dan ID 1/31 hanya memiliki capability konfigurasi/publikasi. Tampilan Request Center empat layanan tidak diubah pada fase ini.

Suite aplikasi **127 test / 613 assertions** lulus, termasuk 19 test khusus Fase 2 dan regresi GA/IT sebelumnya. Syntax PHP/JS dan whitespace diperiksa. Skrip verifikasi dan evidence privat tersedia:

- `scripts/it_phase02_ops.py`: migration/seed pada salinan serta sumber dengan guard backup dan verifikasi.
- `scripts/it_phase02_verify.php`: uji mutasi hanya pada database berprefix verifikasi.
- `scripts/it_phase02_status.php`: GET smoke dan ringkasan status konfigurasi/capability sumber.
- `phase02-mysql-verification.json` dan `phase02-deployment.json` dalam folder backup privat.

Tidak ada pengujian yang membuat keputusan approval atau mengirim notifikasi eksternal nyata.

## Batas dan langkah berikutnya

Fase 2 menyediakan definisi serta publikasi konfigurasi. Routing/penugasan runtime, approval IT, fulfillment, storage privat attachment tiket, SLA/eskalasi, Problem, Change dan CMDB belum diimplementasikan. Service owner dan tim operasional masih perlu ditetapkan sebelum katalog dipakai menerima pengajuan.

**Lanjut Fase 3:** gunakan bundle published saat membuat tiket, ikat versi/form/jawaban, lalu bangun Incident dan Service Request end-to-end. Profil proses baru harus tetap mempertahankan pembaca schema Fase 2; jangan mengubah makna atau isi snapshot lama. Pembukaan submission tetap mengikuti gerbang Fase 3–5 pada plan.
