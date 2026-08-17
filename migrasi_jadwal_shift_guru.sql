-- ============================================================================
--  MIGRASI jadwal_shift_guru: kunci guru dari guru_id -> nip
--  Menyeragamkan seluruh tabel yang merujuk guru agar memakai NIP,
--  sama seperti absensi_guru.nip.
--  NIP diambil dari datacenter_v2.guru (server MySQL yang sama).
-- ============================================================================
USE absensi_sekolah;

CREATE TABLE jadwal_shift_guru_baru (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nip VARCHAR(30) NOT NULL COMMENT 'ref datacenter_v2.guru.nip',
  hari TINYINT NOT NULL COMMENT '1=Senin .. 7=Minggu',
  shift_id INT NOT NULL,
  UNIQUE KEY uk_nip_hari (nip, hari),
  FOREIGN KEY (shift_id) REFERENCES shift(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO jadwal_shift_guru_baru (nip, hari, shift_id)
SELECT g.nip, j.hari, j.shift_id
FROM jadwal_shift_guru j JOIN datacenter_v2.guru g ON g.id = j.guru_id;

DROP TABLE jadwal_shift_guru;
RENAME TABLE jadwal_shift_guru_baru TO jadwal_shift_guru;
