<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once dirname(__DIR__) . '/config/config.php';
$mapping = require dirname(__DIR__) . '/config/mapping.php';

if (!is_array($mapping)) {
    $mapping = [];
}

$db = db_connect();
$today = date('Y-m-d');
$items = [];

/*
 * Build a single parameterized query for all configured poli + dokter pairs.
 * This avoids one database round-trip per mapping while preserving the
 * existing JSON contract and the read-only dashboard design.
 */
$pairs = [];
$types = '';
$params = [$today];
$types .= 's';

foreach ($mapping as $map) {
    $kdPoli = isset($map['kd_poli']) ? trim((string)$map['kd_poli']) : '';
    $kdDokter = isset($map['kd_dokter']) ? trim((string)$map['kd_dokter']) : '';

    if ($kdPoli === '' || $kdDokter === '') {
        continue;
    }

    $pairs[] = '(r.kd_poli = ? AND r.kd_dokter = ?)';
    $types .= 'ss';
    $params[] = $kdPoli;
    $params[] = $kdDokter;

    $items[$kdPoli . '|' . $kdDokter] = [
        'kd_poli' => $kdPoli,
        'nm_poli' => 'Poli',
        'kd_dokter' => $kdDokter,
        'nm_dokter' => 'Dokter',
        'current_number' => null,
        'status' => 'waiting',
    ];
}

if ($pairs) {
    $sql = "
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
          AND a.status = '2'
          AND (" . implode(' OR ', $pairs) . ")
        ORDER BY r.kd_poli ASC, r.kd_dokter ASC, r.jam_reg DESC, r.no_rawat DESC
    ";

    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        mysqli_close($db);
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'date' => $today,
            'updated_at' => date('Y-m-d H:i:s'),
            'items' => [],
            'error' => 'Queue query preparation failed',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $key = $row['kd_poli'] . '|' . $row['kd_dokter'];

            // First row wins because the query is ordered newest-first per pair.
            if (!isset($items[$key]) || $items[$key]['current_number'] !== null) {
                continue;
            }

            $items[$key] = [
                'kd_poli' => $row['kd_poli'],
                'nm_poli' => $row['nm_poli'],
                'kd_dokter' => $row['kd_dokter'],
                'nm_dokter' => $row['nm_dokter'],
                'current_number' => $row['no_reg'],
                'status' => 'called',
            ];
        }
    }

    mysqli_stmt_close($stmt);
}

mysqli_close($db);

echo json_encode([
    'ok' => true,
    'date' => $today,
    'updated_at' => date('Y-m-d H:i:s'),
    'items' => array_values($items),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
