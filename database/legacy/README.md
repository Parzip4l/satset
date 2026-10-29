# Migration bawaan yang diarsipkan

`0001_01_01_000000_create_users_table.php` menduplikasi pembuatan `users`, `sessions`, dan `password_reset_tokens` pada `2025_07_18_095043_create_users_table.php`.

Audit MySQL 28 Oktober 2026 memastikan migration versi domain sudah dijalankan dan migration starter belum pernah dijalankan. Starter dipindahkan ke sini agar tidak dijalankan terhadap tabel existing, tanpa menandai migration fiktif sebagai telah dijalankan atau menghapus data.

Direktori ini tidak dipindai `artisan migrate`. Instalasi baru memakai migration users versi domain setelah migration divisi. Lingkungan lain dengan riwayat berbeda harus diaudit sebelum rollback.
