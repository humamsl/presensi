<?php
require_once __DIR__ . '/config.php';
requireLogin();
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$type   = $_GET['type'] ?? '';     // guru | jabatan | siswa | kelas
$format = $_GET['format'] ?? '';   // excel | pdf
$dari   = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$periode = date('d-m-Y', strtotime($dari)) . ' s/d ' . date('d-m-Y', strtotime($sampai));

$title = ''; $subtitle = ''; $head = []; $body = []; $filename = 'laporan';

if ($type === 'guru' || $type === 'siswa') {
    // Laporan detail harian per orang. Master dari datacenter ($dc), absensi dari $pdo.
    if ($type === 'guru') {
        $id = (int)($_GET['guru_id'] ?? 0);
        $p = dcGuru($dc, $id);
        if (!$p) die('Guru tidak ditemukan.');
        $title = 'Laporan Absensi Guru';
        $subtitle = $p['nama'] . ' (NIP: ' . $p['nip'] . ' — ' . $p['jabatan'] . ') | Periode: ' . $periode;
        $absTable = 'absensi_guru'; $absCol = 'guru_id';
        $filename = 'absensi_guru_' . preg_replace('/[^a-z0-9]+/i', '_', $p['nama']);
    } else {
        $id = (int)($_GET['siswa_id'] ?? 0);
        $taX = tahunAjaranAktif($dc);
        $p = $taX ? dcSiswa($dc, (int)$taX['id'], $id) : null;
        if (!$p) die('Siswa tidak ditemukan.');
        $title = 'Laporan Absensi Siswa';
        $subtitle = $p['nama'] . ' (NIS: ' . $p['nis'] . ' — Kelas ' . ($p['kelas'] ?? '-') . ') | Periode: ' . $periode;
        $absTable = 'absensi_siswa'; $absCol = 'siswa_id';
        $filename = 'absensi_siswa_' . preg_replace('/[^a-z0-9]+/i', '_', $p['nama']);
    }
    // Laporan per tanggal: setiap hari dalam rentang, status dari setting jadwal + catatan absensi
    ['rows' => $lap, 'rekap' => $rekap] = laporanHarian($pdo, $absTable, $absCol, $id, $dari, $sampai);
    $head = ['No', 'Tanggal', 'Hari', 'Jam Masuk', 'Jam Pulang', 'Status', 'Keterangan'];
    $no = 1;
    foreach ($lap as $r) {
        $body[] = [
            $no++,
            date('d-m-Y', strtotime($r['tanggal'])),
            $HARI_ID[date('N', strtotime($r['tanggal'])) - 1],
            $r['jam_masuk'] ? substr($r['jam_masuk'], 0, 5) : '-',
            $r['jam_pulang'] ? substr($r['jam_pulang'], 0, 5) : '-',
            $STATUS_DETAIL[$r['status']],
            $r['keterangan'] ?? '',
        ];
    }
    $footerText = 'Rekap: Hadir ' . $rekap['hadir'] . ' | Terlambat ' . $rekap['terlambat'] . ' | Izin ' . $rekap['izin']
        . ' | Sakit ' . $rekap['sakit'] . ' | Tidak Hadir ' . $rekap['alpha'] . ' | Libur ' . $rekap['libur'];

} elseif ($type === 'jabatan' || $type === 'kelas') {
    // Laporan rekap per grup. Anggota grup dari datacenter ($dc), rekap absensi dari $pdo.
    $members = []; // tiap item: ['noind'=>, 'nama'=>, 'c'=>[status=>n]]
    if ($type === 'jabatan') {
        $grup = trim($_GET['jabatan'] ?? '');
        if ($grup === '') die('Jabatan tidak ditemukan.');
        $title = 'Laporan Rekap Absensi Guru Per Jabatan';
        $subtitle = 'Jabatan: ' . $grup . ' | Periode: ' . $periode;
        $guru = dcGuruList($dc, $grup);
        $rekap = absensiRekap($pdo, 'absensi_guru', 'guru_id', array_column($guru, 'id'), $dari, $sampai);
        foreach ($guru as $g) $members[] = ['noind' => $g['nip'], 'nama' => $g['nama'], 'c' => $rekap[$g['id']] ?? []];
        $head = ['No', 'NIP', 'Nama Guru', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Tidak Hadir', 'Total Hari'];
        $filename = 'rekap_absensi_jabatan_' . preg_replace('/[^a-z0-9]+/i', '_', $grup);
    } else {
        $id = (int)($_GET['kelas_id'] ?? 0);
        $taX = tahunAjaranAktif($dc);
        $kelas = $taX ? dcKelas($dc, (int)$taX['id'], $id) : null;
        if (!$kelas) die('Kelas tidak ditemukan.');
        $grup = $kelas['nama'];
        $title = 'Laporan Rekap Absensi Siswa Per Kelas';
        $subtitle = 'Kelas: ' . $grup . ' | Periode: ' . $periode;
        $siswa = dcSiswaList($dc, (int)$taX['id'], $id);
        $rekap = absensiRekap($pdo, 'absensi_siswa', 'siswa_id', array_column($siswa, 'id'), $dari, $sampai);
        foreach ($siswa as $s) $members[] = ['noind' => $s['nis'], 'nama' => $s['nama'], 'c' => $rekap[$s['id']] ?? []];
        $head = ['No', 'NIS', 'Nama Siswa', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Tidak Hadir', 'Total Hari'];
        $filename = 'rekap_absensi_kelas_' . preg_replace('/[^a-z0-9]+/i', '_', $grup);
    }
    $no = 1;
    foreach ($members as $m) {
        $c = $m['c'];
        $body[] = [$no++, $m['noind'], $m['nama'], (int)($c['hadir'] ?? 0), (int)($c['terlambat'] ?? 0),
                   (int)($c['izin'] ?? 0), (int)($c['sakit'] ?? 0), (int)($c['alpha'] ?? 0), (int)array_sum($c)];
    }
    $footerText = '';
} else {
    die('Parameter type tidak valid.');
}

// ==== Bangun HTML tabel (dipakai Excel & PDF) ====
$html = '<h3 style="margin-bottom:2px">' . e($title) . '</h3>'
      . '<p style="margin-top:0">' . e($subtitle) . '</p>'
      . '<table border="1" cellspacing="0" cellpadding="5" style="border-collapse:collapse;width:100%;font-size:12px">'
      . '<thead><tr style="background:#e2e8f0;font-weight:bold"><th>' . implode('</th><th>', array_map('e', $head)) . '</th></tr></thead><tbody>';
if (!$body) {
    $html .= '<tr><td colspan="' . count($head) . '">Tidak ada data pada periode ini.</td></tr>';
}
foreach ($body as $row) {
    $html .= '<tr><td>' . implode('</td><td>', array_map('e', $row)) . '</td></tr>';
}
$html .= '</tbody></table>';
if ($footerText) $html .= '<p style="font-size:12px"><b>' . e($footerText) . '</b></p>';
$html .= '<p style="font-size:11px;color:#555">Dicetak: ' . date('d-m-Y H:i') . '</p>';

if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo '<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
    exit;
}

if ($format === 'pdf') {
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml('<html><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans,sans-serif;}</style></head><body>' . $html . '</body></html>');
    $dompdf->setPaper('A4', count($head) > 7 ? 'landscape' : 'portrait');
    $dompdf->render();
    $dompdf->stream($filename . '.pdf', ['Attachment' => true]);
    exit;
}

die('Parameter format tidak valid (excel|pdf).');
