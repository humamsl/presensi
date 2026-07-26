# Aplikasi Absensi Sekolah

Aplikasi absensi guru & siswa berbasis PHP 8 + MySQL + Bootstrap 5 + Chart.js.

## Konsep 2 Koneksi Database

Aplikasi memakai **dua koneksi database** (didefinisikan di `config.php`):

1. **`$pdo` → `absensi_sekolah`** — menyimpan data **absensi & konfigurasi** (absensi_siswa, absensi_guru, jadwal, mesin, shift, hari libur, users).
2. **`$dc` → `datacenter_v2`** — sumber data **master** yang dibaca langsung/live (tanpa disalin):
   - siswa → `siswa`
   - guru → `guru`
   - jabatan → kolom teks `guru.jabatan`
   - kelas → `rombongan_belajar` (tahun ajaran aktif)

Tabel absensi menyimpan **id milik datacenter** (`absensi_siswa.siswa_id → datacenter_v2.siswa.id`, `absensi_guru.guru_id → datacenter_v2.guru.id`). Jadi data siswa/guru/jabatan/kelas selalu mengikuti datacenter tanpa perlu sinkronisasi.

## Cara Menjalankan

1. Pastikan Laragon (Apache/Nginx + MySQL) berjalan.
2. Database `absensi_sekolah` sudah dibuat (atau import ulang: `mysql -uroot < database.sql`).
3. Pastikan database **`datacenter_v2`** tersedia di server MySQL yang sama dan punya **tahun ajaran aktif** (`tahun_ajaran.is_aktif=1`). Sesuaikan koneksi di `config.php` bila nama database berbeda.
4. Buka `http://absensi.test` atau `http://localhost/absensi`.
5. Login: **admin / admin123**

## Modul

1. **Dashboard** — total siswa/guru, hadir, tidak hadir, terlambat + grafik 7 hari (hadir, terlambat, izin, sakit, tidak hadir).
2. **Setting Absensi**
   - Jadwal jam absensi guru & siswa (jam masuk, batas terlambat, jam pulang)
   - Mesin absensi (CRUD) & upload data guru/siswa ke mesin (dengan riwayat upload)
   - Hari libur sekolah & nasional (mendukung rentang tanggal)
   - Jadwal shift guru (CRUD shift + penjadwalan per guru per hari)
3. **Info Absensi Guru** — per guru & per jabatan, filter periode tanggal.
4. **Info Absensi Siswa** — per siswa & per kelas, filter periode tanggal.
5. **Export Laporan** — tombol Export Excel (.xls) dan Export PDF (dompdf) tersedia di keempat halaman info absensi (per guru, per jabatan, per siswa, per kelas) mengikuti filter periode yang dipilih. Logika export ada di `export.php`.
6. **Koreksi Absensi** — koreksi/input manual absensi siswa (filter kelas) dan guru per tanggal.

## Catatan

- Tombol "Upload Data" ke mesin saat ini bersifat simulasi (mencatat log upload). Untuk koneksi nyata ke mesin ZKTeco/fingerprint, integrasikan library seperti `php-zklib` pada `setting_mesin.php` (action `upload`).
- Data master siswa/guru/jabatan/kelas **tidak** di-seed di sini — semuanya dibaca langsung dari `datacenter_v2`. `database.sql` hanya berisi tabel absensi & konfigurasi.
- Roster siswa yang tampil = siswa aktif yang **sudah ditempatkan di kelas (rombel) pada tahun ajaran aktif** di datacenter. Siswa yang belum punya rombel di TA aktif akan otomatis muncul setelah ditempatkan di datacenter.
- Konfigurasi kedua koneksi database ada di `config.php`.
