<?php
/**
 * Dashboard Antrean - READ ONLY live monitor.
 * Queue behavior is aligned with the existing monitor and patient view.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

date_default_timezone_set('Asia/Jakarta');

require_once dirname(__DIR__) . '/config/config.php';
$mapping = require dirname(__DIR__) . '/config/mapping.php';
if (!is_array($mapping)) $mapping = [];

function parse_filter_list($value) {
    $value = trim((string)$value);
    if ($value === '') return [];
    $parts = preg_split('/\s*,\s*/', $value);
    $items = [];
    foreach ($parts as $part) {
        $part = trim($part, " \t\r\n\"'");
        if ($part !== '') $items[] = $part;
    }
    return array_values(array_unique($items));
}

function queue_number($noReg) {
    if (preg_match('/(\d+)\s*$/', (string)$noReg, $m)) return (int)$m[1];
    return null;
}

function sort_queue_rows(&$rows) {
    usort($rows, function ($a, $b) {
        $an = queue_number($a['no_reg']);
        $bn = queue_number($b['no_reg']);
        if ($an !== null && $bn !== null && $an !== $bn) return $an < $bn ? -1 : 1;
        if ($an !== null && $bn === null) return -1;
        if ($an === null && $bn !== null) return 1;
        $aj = (string)$a['jam_reg'];
        $bj = (string)$b['jam_reg'];
        if ($aj !== $bj) return $aj < $bj ? -1 : 1;
        return strcmp((string)$a['no_rawat'], (string)$b['no_rawat']);
    });
}

$poliFilter = parse_filter_list($mapping['poli_filter'] ?? '');
$dokterFilter = parse_filter_list($mapping['dokter_filter'] ?? '');
$db = db_connect();
$today = date('Y-m-d');
$now = date('H:i:s');
$dayMap = [
    'Monday'=>'SENIN', 'Tuesday'=>'SELASA', 'Wednesday'=>'RABU',
    'Thursday'=>'KAMIS', 'Friday'=>'JUMAT', 'Saturday'=>'SABTU',
    'Sunday'=>'AKHAD'
];
$hari = $dayMap[date('l')] ?? 'SENIN';

$sql = "SELECT j.kd_poli,p.nm_poli,j.kd_dokter,d.nm_dokter,j.jam_mulai,j.jam_selesai
        FROM jadwal j
        INNER JOIN poliklinik p ON p.kd_poli=j.kd_poli
        INNER JOIN dokter d ON d.kd_dokter=j.kd_dokter
        WHERE j.hari_kerja=?";
$types = 's';
$params = [$hari];
if ($poliFilter) {
    $ph = implode(',', array_fill(0, count($poliFilter), '?'));
    $sql .= " AND j.kd_poli IN ($ph)";
    $types .= str_repeat('s', count($poliFilter));
    foreach ($poliFilter as $v) $params[] = $v;
}
if ($dokterFilter) {
    $ph = implode(',', array_fill(0, count($dokterFilter), '?'));
    $sql .= " AND j.kd_dokter IN ($ph)";
    $types .= str_repeat('s', count($dokterFilter));
    foreach ($dokterFilter as $v) $params[] = $v;
}
$sql .= " ORDER BY j.jam_mulai ASC,j.kd_poli ASC,j.kd_dokter ASC";
$stmt = mysqli_prepare($db, $sql);
if (!$stmt) {
    mysqli_close($db);
    http_response_code(500);
    echo json_encode(['ok'=>false,'date'=>$today,'hari'=>$hari,'updated_at'=>date('Y-m-d H:i:s'),'items'=>[],'error'=>'Schedule query preparation failed'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$schedules = [];
$seen = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row['kd_poli'].'|'.$row['kd_dokter'];
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $schedules[] = $row;
    }
}
mysqli_stmt_close($stmt);

$queueBySchedule = [];
$qsql = "SELECT r.kd_poli,r.kd_dokter,r.no_reg,r.no_rawat,r.jam_reg,r.stts,a.status
         FROM reg_periksa r
         INNER JOIN antripoli a ON a.no_rawat=r.no_rawat
         WHERE r.tgl_registrasi=? AND a.status IN ('0','1','2','3')";
$qstmt = mysqli_prepare($db, $qsql);
if ($qstmt) {
    mysqli_stmt_bind_param($qstmt, 's', $today);
    mysqli_stmt_execute($qstmt);
    $qresult = mysqli_stmt_get_result($qstmt);
    if ($qresult) {
        while ($row = mysqli_fetch_assoc($qresult)) {
            $key = $row['kd_poli'].'|'.$row['kd_dokter'];
            if (!isset($queueBySchedule[$key])) $queueBySchedule[$key] = [];
            $queueBySchedule[$key][] = $row;
        }
    }
    mysqli_stmt_close($qstmt);
}

$items = [];
foreach ($schedules as $schedule) {
    $key = $schedule['kd_poli'].'|'.$schedule['kd_dokter'];
    $queueRows = $queueBySchedule[$key] ?? [];
    sort_queue_rows($queueRows);
    $calledRows = array_values(array_filter($queueRows, function($r) { return (string)$r['status'] === '2'; }));
    $currentCalled = end($calledRows);
    $currentCalled = $currentCalled === false ? null : $currentCalled;
    $operationalRows = array_values(array_filter($queueRows, function($r) { return in_array((string)$r['status'], ['1','2','3'], true); }));
    usort($operationalRows, function($a, $b) {
        $cmp = strcmp((string)$b['no_rawat'], (string)$a['no_rawat']);
        if ($cmp !== 0) return $cmp;
        return strcmp((string)$b['jam_reg'], (string)$a['jam_reg']);
    });
    $latestOperational = $operationalRows[0] ?? null;
    $displayRow = $currentCalled ?: $latestOperational;
    $displayNumber = $displayRow['no_reg'] ?? null;
    $displayNumberNumeric = $displayRow ? queue_number($displayRow['no_reg']) : null;
    $displaySourceStatus = $displayRow ? (string)$displayRow['status'] : null;
    $statusLabel = 'Belum Ada Panggilan';
    if ($displaySourceStatus === '2') $statusLabel = 'Sedang Dipanggil';
    elseif ($displaySourceStatus === '1') $statusLabel = 'Berikutnya';
    elseif ($displaySourceStatus === '3') $statusLabel = 'Nomor Terakhir';
    $waitingTotal = count(array_filter($queueRows, function($r) { return (string)$r['status'] === '0'; }));
    $nextTotal = count(array_filter($queueRows, function($r) { return (string)$r['status'] === '1'; }));
    $calledTotal = count($calledRows);
    $passedTotal = count(array_filter($queueRows, function($r) { return (string)$r['status'] === '3'; }));
    $totalQueue = count($queueRows);
    $servedTotal = $calledTotal + $passedTotal;
    $progressPercent = $totalQueue > 0 ? (int)round(($servedTotal / $totalQueue) * 100) : 0;
    $mulai = $schedule['jam_mulai'];
    $selesai = $schedule['jam_selesai'];
    if ($now < $mulai) {
        $scheduleStatus = 'before';
        $statusLabel = 'Belum Mulai';
    } elseif ($selesai !== null && $now > $selesai) {
        $scheduleStatus = 'finished';
        $statusLabel = 'Selesai';
    } elseif ($currentCalled !== null) {
        $scheduleStatus = 'called';
        $statusLabel = 'Sedang Dipanggil';
    } elseif ($latestOperational !== null) {
        $scheduleStatus = 'empty';
        if ($displaySourceStatus === '1') $statusLabel = 'Berikutnya';
        elseif ($displaySourceStatus === '3') $statusLabel = 'Nomor Terakhir';
    } else {
        $scheduleStatus = 'empty';
        $statusLabel = 'Belum Ada Panggilan';
    }
    $items[] = [
        'kd_poli'=>$schedule['kd_poli'], 'nm_poli'=>$schedule['nm_poli'],
        'kd_dokter'=>$schedule['kd_dokter'], 'nm_dokter'=>$schedule['nm_dokter'],
        'jam_mulai'=>$mulai, 'jam_selesai'=>$selesai,
        'current_number'=>$displayNumber, 'current_number_numeric'=>$displayNumberNumeric,
        'current_queue_status'=>$displaySourceStatus,
        'active_call_number'=>$currentCalled['no_reg'] ?? null,
        'last_operational_number'=>$latestOperational['no_reg'] ?? null,
        'called_total'=>$calledTotal, 'waiting_total'=>$waitingTotal,
        'next_total'=>$nextTotal, 'passed_total'=>$passedTotal,
        'queue_total'=>$totalQueue, 'served_total'=>$servedTotal,
        'progress_percent'=>$progressPercent, 'schedule_status'=>$scheduleStatus,
        'status_label'=>$statusLabel
    ];
}
mysqli_close($db);
echo json_encode([
    'ok'=>true, 'date'=>$today, 'hari'=>$hari, 'server_time'=>$now,
    'updated_at'=>date('Y-m-d H:i:s'), 'read_only'=>true,
    'queue_semantics'=>[
        'status_waiting'=>'0', 'status_next'=>'1', 'status_called'=>'2', 'status_passed'=>'3',
        'active_call_rule'=>'status_2', 'monitor_fallback_rule'=>'latest_no_rawat_among_status_1_2_3'
    ],
    'items'=>$items
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
