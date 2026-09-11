<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once dirname(__DIR__) . '/config/config.php';
$mapping = require dirname(__DIR__) . '/config/mapping.php';

if (!is_array($mapping)) {
    $mapping = [];
}

/* Supports filters such as: "'U003','U053','INT','OBG'". */
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

$poliFilter   = parse_filter_list($mapping['poli_filter'] ?? '');
$dokterFilter = parse_filter_list($mapping['dokter_filter'] ?? '');

$db = db_connect();
$today = date('Y-m-d');
$dayMap = [
    'Monday'    => 'SENIN',
    'Tuesday'   => 'SELASA',
    'Wednesday' => 'RABU',
    'Thursday'  => 'KAMIS',
    'Friday'    => 'JUMAT',
    'Saturday'  => 'SABTU',
    'Sunday'    => 'AKHAD',
];
$hari = $dayMap[date('l')] ?? 'SENIN';

/*
 * The display cards are driven by jadwal, not by calls.
 * A poli + dokter is shown only while today's schedule is active.
 * The latest status=2 call is attached when available; otherwise the card
 * remains visible with status=waiting.
 */
$sql = "
    SELECT
        j.kd_poli,
        p.nm_poli,
        j.kd_dokter,
        d.nm_dokter,
        j.jam_mulai,
        j.jam_selesai,
        c.no_reg AS current_number
    FROM jadwal j
    INNER JOIN poliklinik p ON p.kd_poli = j.kd_poli
    INNER JOIN dokter d ON d.kd_dokter = j.kd_dokter
    LEFT JOIN (
        SELECT
            r.kd_poli,
            r.kd_dokter,
            r.no_reg
        FROM reg_periksa r
        INNER JOIN antripoli a ON a.no_rawat = r.no_rawat
        WHERE r.tgl_registrasi = ?
          AND a.status = '2'
          AND NOT EXISTS (
              SELECT 1
              FROM reg_periksa r2
              INNER JOIN antripoli a2 ON a2.no_rawat = r2.no_rawat
              WHERE r2.tgl_registrasi = r.tgl_registrasi
                AND r2.kd_poli = r.kd_poli
                AND r2.kd_dokter = r.kd_dokter
                AND a2.status = '2'
                AND (
                    r2.jam_reg > r.jam_reg
                    OR (r2.jam_reg = r.jam_reg AND r2.no_rawat > r.no_rawat)
                )
          )
    ) c ON c.kd_poli = j.kd_poli AND c.kd_dokter = j.kd_dokter
    WHERE j.hari_kerja = ?
      AND j.jam_mulai <= CURTIME()
      AND (j.jam_selesai IS NULL OR j.jam_selesai >= CURTIME())
";

$types = 'ss';
$params = [$today, $hari];

if ($poliFilter) {
    $placeholders = implode(',', array_fill(0, count($poliFilter), '?'));
    $sql .= " AND j.kd_poli IN ($placeholders)";
    $types .= str_repeat('s', count($poliFilter));
    foreach ($poliFilter as $value) {
        $params[] = $value;
    }
}

if ($dokterFilter) {
    $placeholders = implode(',', array_fill(0, count($dokterFilter), '?'));
    $sql .= " AND j.kd_dokter IN ($placeholders)";
    $types .= str_repeat('s', count($dokterFilter));
    foreach ($dokterFilter as $value) {
        $params[] = $value;
    }
}

$sql .= "
    ORDER BY j.jam_mulai ASC, j.kd_poli ASC, j.kd_dokter ASC
";

$stmt = mysqli_prepare($db, $sql);

if (!$stmt) {
    mysqli_close($db);
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'date' => $today,
        'hari' => $hari,
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

        // Prevent duplicate cards when jadwal contains overlapping entries.
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $items[] = [
            'kd_poli' => $row['kd_poli'],
            'nm_poli' => $row['nm_poli'],
            'kd_dokter' => $row['kd_dokter'],
            'nm_dokter' => $row['nm_dokter'],
            'jam_mulai' => $row['jam_mulai'],
            'jam_selesai' => $row['jam_selesai'],
            'current_number' => $row['current_number'] ?? null,
            'status' => $row['current_number'] !== null ? 'called' : 'waiting',
            'schedule_active' => true,
        ];
    }
}

mysqli_stmt_close($stmt);
mysqli_close($db);

echo json_encode([
    'ok' => true,
    'date' => $today,
    'hari' => $hari,
    'updated_at' => date('Y-m-d H:i:s'),
    'items' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
