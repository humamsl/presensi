-- ============================================================================
--  MIGRASI absensi_siswa: dari 1 baris per hari  ->  LOG EVENT (1 baris = 1 kejadian)
--  Struktur baru: id, nis, tanggal, jam, status, keterangan
--  Kode status: 0=masuk, 1=pulang, 2=sakit, 3=ijin, 4=alpha
--  NIS diambil dari datacenter_v2.siswa (server MySQL yang sama).
-- ============================================================================
USE absensi_sekolah;

CREATE TABLE absensi_siswa_baru (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nis VARCHAR(30) NOT NULL COMMENT 'ref datacenter_v2.siswa.nis (fallback nisn)',
  tanggal DATE NOT NULL,
  jam TIME DEFAULT NULL,
  status TINYINT NOT NULL COMMENT '0=masuk,1=pulang,2=sakit,3=ijin,4=alpha',
  keterangan VARCHAR(200) DEFAULT NULL,
  UNIQUE KEY uk_nis_tgl_status (nis, tanggal, status),
  KEY idx_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kehadiran lama -> event masuk (0)
INSERT IGNORE INTO absensi_siswa_baru (nis, tanggal, jam, status, keterangan)
SELECT COALESCE(NULLIF(s.nis,''), s.nisn), a.tanggal, a.jam_masuk, 0, a.keterangan
FROM absensi_siswa a JOIN datacenter_v2.siswa s ON s.id = a.siswa_id
WHERE a.jam_masuk IS NOT NULL;

-- Kehadiran lama -> event pulang (1)
INSERT IGNORE INTO absensi_siswa_baru (nis, tanggal, jam, status, keterangan)
SELECT COALESCE(NULLIF(s.nis,''), s.nisn), a.tanggal, a.jam_pulang, 1, NULL
FROM absensi_siswa a JOIN datacenter_v2.siswa s ON s.id = a.siswa_id
WHERE a.jam_pulang IS NOT NULL;

-- Ketidakhadiran lama -> kode 2/3/4
INSERT IGNORE INTO absensi_siswa_baru (nis, tanggal, jam, status, keterangan)
SELECT COALESCE(NULLIF(s.nis,''), s.nisn), a.tanggal, NULL,
       CASE a.status WHEN 'sakit' THEN 2 WHEN 'izin' THEN 3 ELSE 4 END, a.keterangan
FROM absensi_siswa a JOIN datacenter_v2.siswa s ON s.id = a.siswa_id
WHERE a.status IN ('sakit','izin','alpha');

DROP TABLE absensi_siswa;
RENAME TABLE absensi_siswa_baru TO absensi_siswa;
