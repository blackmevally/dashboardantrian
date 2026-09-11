<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once dirname(__DIR__) . '/config/config.php';
$mapping = require dirname(__DIR__) . '/config/mapping.php';

if (!is_array($mapping)) {
    $mapping = [];
}

/*
 * Supports filters such as:
 *   "'U003','U053','INT','OBG'"
 * Empty filter means no restriction.
 */
function parse_filter_list($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return [];
    }

    $parts = preg_split('/\s*,\s*/', $value);
    $items = [];

    foreach ($parts as $part) {
        $part = trim($part);
        $part = trim($part, " \t\r\n\"'");
        if ($part !== '') {
            $items[] = $part;
        }
    }

    return array_values(array_unique($items));
}

$poliFilter = parse_filter_list($mapping['poli_filter'] ?? '');
$dokterFilter = parse_filter_list($mapping['dokter_filter'] ?? '');

$db = db_connect();
$today = date('Y-m-d');

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
";

$types = 's';
$params = [$today];

if ($poliFilter) {
    $placeholders = implode(',', array_fill(0, count($poliFilter), '?'));
    $sql .= " AND r.kd_poli IN ($placeholders)";
    $types .= str_repeat('s', count($poliFilter));
    foreach ($poliFilter as $value) {
        $params[] = $value;
    }
}

if ($dokterFilter) {
    $placeholders = implode(',', array_fill(0, count($dokterFilter), '?'));
    $sql .= " AND r.kd_dokter IN ($placeholders)";
    $types .= str_repeat('s', count($dokterFilter));
    foreach ($dokterFilter as $value) {
        $params[] = $value;
    }
}

$sql .= "
    ORDER BY
        r.kd_poli ASC,
        r.kd_dokter ASC,
        r.jam_reg DESC,
        r.no_rawat DESC
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

$items = [];
$seen = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row['kd_poli'] . '|' . $row['kd_dokter'];

        // Keep only the newest currently-called number for each poli + dokter pair.
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $items[] = [
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
mysqli_close($db);

echo json_encode([
    'ok' => true,
    'date' => $today,
    'updated_at' => date('Y-m-d H:i:s'),
    'items' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
