-- ============================================================================
--  MIGRASI mesin_absensi: hapus kolom ip & port
-- ----------------------------------------------------------------------------
--  Sejak semua komunikasi memakai ADMS, mesin yang menghubungi server (bukan
--  sebaliknya), sehingga alamat IP mesin tidak pernah dipakai aplikasi.
--  Identitas mesin sepenuhnya memakai serial_number.
-- ============================================================================
USE absensi_sekolah;

ALTER TABLE mesin_absensi
  DROP COLUMN ip,
  DROP COLUMN port;
