-- ============================================================================
--  MIGRASI absensi_guru: dari 1 baris per hari  ->  LOG EVENT (1 baris = 1 kejadian)
--  Struktur baru disamakan dengan absensi_siswa:
--     id, nip, tanggal, jam, status, keterangan
--  Kode status: 0=masuk, 1=pulang, 2=sakit, 3=ijin, 4=alpha, 5=dinas luar, 6=cuti
--  NIP diambil dari datacenter_v2.guru (server MySQL yang sama).
-- ============================================================================
USE absensi_sekolah;

CREATE TABLE absensi_guru_baru (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nip VARCHAR(30) NOT NULL COMMENT 'ref datacenter_v2.guru.nip',
  tanggal DATE NOT NULL,
  jam TIME DEFAULT NULL,
  status TINYINT NOT NULL COMMENT '0=masuk,1=pulang,2=sakit,3=ijin,4=alpha,5=dinas luar,6=cuti',
  keterangan VARCHAR(200) DEFAULT NULL,
  UNIQUE KEY uk_nip_tgl_status (nip, tanggal, status),
  KEY idx_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kehadiran lama -> event masuk (0)
INSERT IGNORE INTO absensi_guru_baru (nip, tanggal, jam, status, keterangan)
SELECT g.nip, a.tanggal, a.jam_masuk, 0, a.keterangan
FROM absensi_guru a JOIN datacenter_v2.guru g ON g.id = a.guru_id
WHERE a.jam_masuk IS NOT NULL;

-- Kehadiran lama -> event pulang (1)
INSERT IGNORE INTO absensi_guru_baru (nip, tanggal, jam, status, keterangan)
SELECT g.nip, a.tanggal, a.jam_pulang, 1, NULL
FROM absensi_guru a JOIN datacenter_v2.guru g ON g.id = a.guru_id
WHERE a.jam_pulang IS NOT NULL;

-- Ketidakhadiran lama -> kode 2/3/4
INSERT IGNORE INTO absensi_guru_baru (nip, tanggal, jam, status, keterangan)
SELECT g.nip, a.tanggal, NULL,
       CASE a.status WHEN 'sakit' THEN 2 WHEN 'izin' THEN 3 ELSE 4 END, a.keterangan
FROM absensi_guru a JOIN datacenter_v2.guru g ON g.id = a.guru_id
WHERE a.status IN ('sakit','izin','alpha');

DROP TABLE absensi_guru;
RENAME TABLE absensi_guru_baru TO absensi_guru;

-- Samakan komentar kode status pada absensi_siswa (kode 5 & 6 kini dikenal juga)
ALTER TABLE absensi_siswa
  MODIFY status TINYINT NOT NULL
  COMMENT '0=masuk,1=pulang,2=sakit,3=ijin,4=alpha,5=dinas luar,6=cuti';
