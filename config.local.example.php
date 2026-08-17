<?php
/*
 * ============================================================================
 *  CONTOH PENGATURAN PER SERVER
 * ----------------------------------------------------------------------------
 *  Cara pakai saat memasang di server baru (Virtualmin, cPanel, VPS, dsb):
 *
 *    1. Salin berkas ini menjadi  config.local.php  di folder yang sama.
 *    2. Sesuaikan nilainya dengan database di server tersebut.
 *    3. Selesai — tidak ada berkas kode lain yang perlu diubah.
 *
 *  config.local.php diabaikan git, jadi setelan tiap server tidak saling
 *  menimpa saat Anda menarik pembaruan kode.
 *
 *  Alternatif: kalau panel hosting Anda mendukung variabel lingkungan,
 *  nilai yang sama bisa diisikan di sana dan berkas ini tidak perlu dibuat.
 * ============================================================================
 */

return [
    // ---- Database absensi (data absensi & konfigurasi aplikasi) ----
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'absensi_sekolah',
    'DB_USER' => 'root',
    'DB_PASS' => '',

    // ---- Database datacenter (sumber data master siswa/guru/kelas) ----
    // Di hosting, biasanya nama database diberi awalan akun, mis. 'sekolah_datacenter'.
    'DC_HOST' => '127.0.0.1',
    'DC_PORT' => '3306',
    'DC_NAME' => 'datacenter_v2',
    'DC_USER' => 'root',
    'DC_PASS' => '',
];
