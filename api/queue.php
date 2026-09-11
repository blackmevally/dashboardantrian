<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once dirname(__DIR__) . '/config/config.php';
$mapping = require dirname(__DIR__) . '/config/mapping.php';

$db = db_connect();

$items = [];
$today = date('Y-m-d');

if (!is_array($mapping)) {
    $mapping = [];
}

foreach ($mapping as $map) {
    $kdPoli = isset($map['kd_poli']) ? trim((string)$map['kd_poli']) : '';
    $kdDokter = isset($map['kd_dokter']) ? trim((string)$map['kd_dokter']) : '';
    if ($kdPoli === '' || $kdDokter === '') {
        continue;
    }

    $stmt = mysqli_prepare($db, "
        SELECT
            p.kd_poli,
            p.nm_poli,
            d.kd_dokter,
            d.nm_dokter,
            r.no_reg,
            a.status
        FROM antripoli a
        INNER JOIN reg_periksa r ON r.no_rawat = a.no_rawat
        INNER JOIN poliklinik p ON p.kd_poli = r.kd_poli
        INNER JOIN dokter d ON d.kd_dokter = r.kd_dokter
        WHERE r.tgl_registrasi = ?
          AND r.kd_poli = ?
          AND r.kd_dokter = ?
          AND a.status = '2'
        ORDER BY r.jam_reg DESC, r.no_rawat DESC
        LIMIT 1
    ");

    mysqli_stmt_bind_param($stmt, 'sss', $today, $kdPoli, $kdDokter);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    $items[] = [
        'kd_poli' => $row['kd_poli'] ?? $kdPoli,
        'nm_poli' => $row['nm_poli'] ?? 'Poli',
        'kd_dokter' => $row['kd_dokter'] ?? $kdDokter,
        'nm_dokter' => $row['nm_dokter'] ?? 'Dokter',
        'current_number' => $row['no_reg'] ?? null,
        'status' => $row ? 'called' : 'waiting',
    ];
}

mysqli_close($db);

echo json_encode([
    'ok' => true,
    'date' => $today,
    'updated_at' => date('Y-m-d H:i:s'),
    'items' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
