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

Tabel absensi merujuk data datacenter lewat nomor induk (`absensi_siswa.nis → datacenter_v2.siswa.nis`, `absensi_guru.nip → datacenter_v2.guru.nip`). Jadi data siswa/guru/jabatan/kelas selalu mengikuti datacenter tanpa perlu sinkronisasi.

## Struktur Tabel Absensi (log event)

`absensi_siswa` dan `absensi_guru` memakai **struktur yang sama** — satu baris = satu kejadian:

| Kolom | Keterangan |
|---|---|
| `id` | primary key |
| `nis` / `nip` | nomor induk siswa (fallback NISN) atau guru, merujuk datacenter |
| `tanggal` | tanggal kejadian |
| `jam` | jam scan (diisi untuk kode 0 & 1, boleh NULL untuk kode 2–6) |
| `status` | kode status (lihat tabel di bawah) |
| `keterangan` | catatan bebas |

### Kode status

| Kode | Arti | Kolom `jam` |
|---|---|---|
| 0 | masuk | jam scan masuk |
| 1 | pulang | jam scan pulang |
| 2 | sakit | NULL |
| 3 | ijin | NULL |
| 4 | alpha (tidak hadir) | NULL |
| 5 | dinas luar | NULL |
| 6 | cuti | NULL |

Satu orang pada satu tanggal bisa punya beberapa baris, misalnya masuk (kode 0, jam 06:45) dan pulang (kode 1, jam 14:05). Ketidakhadiran cukup satu baris (kode 2–6).

**Kode 5 (dinas luar) dan 6 (cuti)** berlaku untuk guru; dropdown koreksi siswa hanya menawarkan kode 2–4. Struktur tabelnya tetap sama sehingga kode ini bisa dipakai untuk siswa bila nanti diperlukan — cukup ubah `kodeKetidakhadiran()` di `config.php`.

Status **hadir** dan **terlambat** tidak disimpan di database — keduanya dihitung otomatis dari jam masuk dibandingkan `batas_terlambat` pada Setting Jadwal hari yang bersangkutan. Hari yang tidak ada catatannya dihitung **Tidak Hadir** bila hari sekolah/kerja, atau **Libur** bila jatuh pada hari libur/akhir pekan.

Pada dashboard, guru berstatus **dinas luar dihitung hadir** (tetap bertugas), sedangkan **cuti tidak**.

Migrasi dari struktur lama ada di `migrasi_absensi_siswa.sql` dan `migrasi_absensi_guru.sql`.

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
6. **Koreksi Absensi** — koreksi/input manual per tanggal untuk siswa (filter kelas) dan guru. Pilih *Hadir* lalu isi jam masuk/pulang (tersimpan sebagai kode 0 & 1), atau pilih kode ketidakhadiran (siswa: Sakit/Ijin/Alpha; guru: ditambah Dinas Luar/Cuti) yang tersimpan sebagai satu baris kode 2–6.

## Catatan

- Tombol "Upload Data" ke mesin saat ini bersifat simulasi (mencatat log upload). Untuk koneksi nyata ke mesin ZKTeco/fingerprint, integrasikan library seperti `php-zklib` pada `setting_mesin.php` (action `upload`).
- Data master siswa/guru/jabatan/kelas **tidak** di-seed di sini — semuanya dibaca langsung dari `datacenter_v2`. `database.sql` hanya berisi tabel absensi & konfigurasi.
- Roster siswa yang tampil = siswa aktif yang **sudah ditempatkan di kelas (rombel) pada tahun ajaran aktif** di datacenter. Siswa yang belum punya rombel di TA aktif akan otomatis muncul setelah ditempatkan di datacenter.
- Konfigurasi kedua koneksi database ada di `config.php`.
