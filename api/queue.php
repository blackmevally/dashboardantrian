<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once dirname(__DIR__) . '/config/config.php';
$mapping = require dirname(__DIR__) . '/config/mapping.php';

if (!is_array($mapping)) {
    $mapping = [];
}

function parse_filter_list($value) {
    $value = trim((string)$value);
    if ($value === '') return [];

    $parts = preg_split('/\s*,\s*/', $value);
    $items = [];
    foreach ($parts as $part) {
        $part = trim($part);
        $part = trim($part, " \t\r\n\"'");
        if ($part !== '') $items[] = $part;
    }
    return array_values(array_unique($items));
}

/* Same queue-number rule used by pasienrspm/ui-v2. */
function queue_number($noReg) {
    if (preg_match('/(\d+)\s*$/', (string)$noReg, $m)) {
        return (int)$m[1];
    }
    return null;
}

/* Same ordering contract as pasienrspm/ui-v2. */
function sort_queue_rows(&$rows) {
    usort($rows, function ($a, $b) {
        $an = queue_number($a['no_reg']);
        $bn = queue_number($b['no_reg']);

        if ($an !== null && $bn !== null && $an !== $bn) {
            return $an < $bn ? -1 : 1;
        }
        if ($an !== null && $bn === null) return -1;
        if ($an === null && $bn !== null) return 1;

        $aj = (string)$a['jam_reg'];
        $bj = (string)$b['jam_reg'];
        if ($aj !== $bj) return $aj < $bj ? -1 : 1;

        return strcmp((string)$a['no_rawat'], (string)$b['no_rawat']);
    });
}

$poliFilter   = parse_filter_list($mapping['poli_filter'] ?? '');
$dokterFilter = parse_filter_list($mapping['dokter_filter'] ?? '');

$db = db_connect();
$today = date('Y-m-d');
$now = date('H:i:s');
$dayMap = [
    'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU',
    'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU',
    'Sunday' => 'AKHAD',
];
$hari = $dayMap[date('l')] ?? 'SENIN';

/* Public cards come from jadwal; queue state is attached separately. */
$sql = "
    SELECT
        j.kd_poli,
        p.nm_poli,
        j.kd_dokter,
        d.nm_dokter,
        j.jam_mulai,
        j.jam_selesai
    FROM jadwal j
    INNER JOIN poliklinik p ON p.kd_poli = j.kd_poli
    INNER JOIN dokter d ON d.kd_dokter = j.kd_dokter
    WHERE j.hari_kerja = ?
";

$types = 's';
$params = [$hari];

if ($poliFilter) {
    $placeholders = implode(',', array_fill(0, count($poliFilter), '?'));
    $sql .= " AND j.kd_poli IN ($placeholders)";
    $types .= str_repeat('s', count($poliFilter));
    foreach ($poliFilter as $value) $params[] = $value;
}

if ($dokterFilter) {
    $placeholders = implode(',', array_fill(0, count($dokterFilter), '?'));
    $sql .= " AND j.kd_dokter IN ($placeholders)";
    $types .= str_repeat('s', count($dokterFilter));
    foreach ($dokterFilter as $value) $params[] = $value;
}

$sql .= " ORDER BY j.jam_mulai ASC, j.kd_poli ASC, j.kd_dokter ASC";

$stmt = mysqli_prepare($db, $sql);
if (!$stmt) {
    mysqli_close($db);
    http_response_code(500);
    echo json_encode([
        'ok' => false, 'date' => $today, 'hari' => $hari,
        'updated_at' => date('Y-m-d H:i:s'), 'items' => [],
        'error' => 'Schedule query preparation failed'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$schedules = [];
$seen = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row['kd_poli'] . '|' . $row['kd_dokter'];
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $schedules[] = $row;
    }
}

mysqli_stmt_close($stmt);

/*
 * Read all today's status=2 rows once and group them by doctor/poli.
 * Status 1 remains "berikutnya" and status 3 remains passed/completed.
 */
$calledBySchedule = [];
$calledSql = "
    SELECT
        r.kd_poli,
        r.kd_dokter,
        r.no_reg,
        r.no_rawat,
        r.jam_reg
    FROM reg_periksa r
    INNER JOIN antripoli a ON a.no_rawat = r.no_rawat
    WHERE r.tgl_registrasi = ?
      AND a.status = '2'
";

$calledStmt = mysqli_prepare($db, $calledSql);
if ($calledStmt) {
    mysqli_stmt_bind_param($calledStmt, 's', $today);
    mysqli_stmt_execute($calledStmt);
    $calledResult = mysqli_stmt_get_result($calledStmt);

    if ($calledResult) {
        while ($row = mysqli_fetch_assoc($calledResult)) {
            $key = $row['kd_poli'] . '|' . $row['kd_dokter'];
            if (!isset($calledBySchedule[$key])) {
                $calledBySchedule[$key] = [];
            }
            $calledBySchedule[$key][] = $row;
        }
    }

    mysqli_stmt_close($calledStmt);
}

$items = [];

foreach ($schedules as $row) {
    $key = $row['kd_poli'] . '|' . $row['kd_dokter'];
    $calledRows = $calledBySchedule[$key] ?? [];
    sort_queue_rows($calledRows);

    /* Highest currently-called queue number = public current call. */
    $current = !empty($calledRows) ? $calledRows[count($calledRows) - 1] : null;
    $currentNumber = $current ? $current['no_reg'] : null;

    $mulai = $row['jam_mulai'];
    $selesai = $row['jam_selesai'];

    if ($now < $mulai) {
        $scheduleStatus = 'before';
        $statusLabel = 'Belum Mulai';
        $displayNumber = null;
    } elseif ($selesai !== null && $now > $selesai) {
        $scheduleStatus = 'finished';
        $statusLabel = 'Selesai';
        $displayNumber = null;
    } elseif ($currentNumber !== null) {
        $scheduleStatus = 'called';
        $statusLabel = 'Sedang Dipanggil';
        $displayNumber = $currentNumber;
    } else {
        $scheduleStatus = 'empty';
        $statusLabel = 'Belum Ada Panggilan';
        $displayNumber = null;
    }

    $items[] = [
        'kd_poli' => $row['kd_poli'],
        'nm_poli' => $row['nm_poli'],
        'kd_dokter' => $row['kd_dokter'],
        'nm_dokter' => $row['nm_dokter'],
        'jam_mulai' => $mulai,
        'jam_selesai' => $selesai,
        'current_number' => $displayNumber,
        'current_number_numeric' => $current ? queue_number($current['no_reg']) : null,
        'current_queue_status' => $current ? '2' : null,
        'called_total' => count($calledRows),
        'schedule_status' => $scheduleStatus,
        'status_label' => $statusLabel,
    ];
}

mysqli_close($db);

echo json_encode([
    'ok' => true,
    'date' => $today,
    'hari' => $hari,
    'server_time' => $now,
    'updated_at' => date('Y-m-d H:i:s'),
    'queue_semantics' => [
        'status_waiting' => '0',
        'status_next' => '1',
        'status_called' => '2',
        'status_passed' => '3',
        'current_call_rule' => 'highest_numeric_no_reg_with_status_2',
    ],
    'items' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
