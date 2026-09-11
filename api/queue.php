<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

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
        $an = queue_number($a['no_reg']); $bn = queue_number($b['no_reg']);
        if ($an !== null && $bn !== null && $an !== $bn) return $an < $bn ? -1 : 1;
        if ($an !== null && $bn === null) return -1;
        if ($an === null && $bn !== null) return 1;
        $aj = (string)$a['jam_reg']; $bj = (string)$b['jam_reg'];
        if ($aj !== $bj) return $aj < $bj ? -1 : 1;
        return strcmp((string)$a['no_rawat'], (string)$b['no_rawat']);
    });
}

$poliFilter = parse_filter_list($mapping['poli_filter'] ?? '');
$dokterFilter = parse_filter_list($mapping['dokter_filter'] ?? '');
$db = db_connect();
$today = date('Y-m-d');
$now = date('H:i:s');
$dayMap = ['Monday'=>'SENIN','Tuesday'=>'SELASA','Wednesday'=>'RABU','Thursday'=>'KAMIS','Friday'=>'JUMAT','Saturday'=>'SABTU','Sunday'=>'AKHAD'];
$hari = $dayMap[date('l')] ?? 'SENIN';

$sql = "SELECT j.kd_poli,p.nm_poli,j.kd_dokter,d.nm_dokter,j.jam_mulai,j.jam_selesai FROM jadwal j INNER JOIN poliklinik p ON p.kd_poli=j.kd_poli INNER JOIN dokter d ON d.kd_dokter=j.kd_dokter WHERE j.hari_kerja=?";
$types = 's'; $params = [$hari];
if ($poliFilter) {
    $ph = implode(',', array_fill(0,count($poliFilter),'?')); $sql .= " AND j.kd_poli IN ($ph)"; $types .= str_repeat('s',count($poliFilter));
    foreach ($poliFilter as $v) $params[] = $v;
}
if ($dokterFilter) {
    $ph = implode(',', array_fill(0,count($dokterFilter),'?')); $sql .= " AND j.kd_dokter IN ($ph)"; $types .= str_repeat('s',count($dokterFilter));
    foreach ($dokterFilter as $v) $params[] = $v;
}
$sql .= " ORDER BY j.jam_mulai ASC,j.kd_poli ASC,j.kd_dokter ASC";
$stmt = mysqli_prepare($db,$sql);
if (!$stmt) { mysqli_close($db); http_response_code(500); echo json_encode(['ok'=>false,'date'=>$today,'hari'=>$hari,'updated_at'=>date('Y-m-d H:i:s'),'items'=>[],'error'=>'Schedule query preparation failed'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
mysqli_stmt_bind_param($stmt,$types,...$params); mysqli_stmt_execute($stmt); $result=mysqli_stmt_get_result($stmt);
$schedules=[]; $seen=[];
if ($result) while ($row=mysqli_fetch_assoc($result)) { $key=$row['kd_poli'].'|'.$row['kd_dokter']; if(isset($seen[$key])) continue; $seen[$key]=true; $schedules[]=$row; }
mysqli_stmt_close($stmt);

/* Read all queue states once so the TV can show meaningful service progress. */
$queueBySchedule=[];
$qsql="SELECT r.kd_poli,r.kd_dokter,r.no_reg,r.no_rawat,r.jam_reg,a.status FROM reg_periksa r INNER JOIN antripoli a ON a.no_rawat=r.no_rawat WHERE r.tgl_registrasi=? AND a.status IN ('0','1','2','3')";
$qstmt=mysqli_prepare($db,$qsql);
if($qstmt){
    mysqli_stmt_bind_param($qstmt,'s',$today); mysqli_stmt_execute($qstmt); $qresult=mysqli_stmt_get_result($qstmt);
    if($qresult) while($row=mysqli_fetch_assoc($qresult)){ $key=$row['kd_poli'].'|'.$row['kd_dokter']; if(!isset($queueBySchedule[$key])) $queueBySchedule[$key]=[]; $queueBySchedule[$key][]=$row; }
    mysqli_stmt_close($qstmt);
}

$items=[];
foreach($schedules as $row){
    $key=$row['kd_poli'].'|'.$row['kd_dokter']; $queueRows=$queueBySchedule[$key]??[]; sort_queue_rows($queueRows);
    $calledRows=array_values(array_filter($queueRows,fn($r)=>(string)$r['status']==='2'));
    $current=end($calledRows); $current=$current===false?null:$current;
    $totalQueue=count($queueRows);
    $servedTotal=count(array_filter($queueRows,fn($r)=>(string)$r['status']==='3'))+count($calledRows);
    $waitingTotal=count(array_filter($queueRows,fn($r)=>(string)$r['status']==='0'));
    $nextTotal=count(array_filter($queueRows,fn($r)=>(string)$r['status']==='1'));
    $progressPercent=$totalQueue>0?(int)round(($servedTotal/$totalQueue)*100):0;
    $mulai=$row['jam_mulai']; $selesai=$row['jam_selesai'];
    if($now<$mulai){$scheduleStatus='before';$statusLabel='Belum Mulai';$displayNumber=null;}
    elseif($selesai!==null&&$now>$selesai){$scheduleStatus='finished';$statusLabel='Selesai';$displayNumber=null;}
    elseif($current!==null){$scheduleStatus='called';$statusLabel='Sedang Dipanggil';$displayNumber=$current['no_reg'];}
    else{$scheduleStatus='empty';$statusLabel='Belum Ada Panggilan';$displayNumber=null;}
    $items[]=[
        'kd_poli'=>$row['kd_poli'],'nm_poli'=>$row['nm_poli'],'kd_dokter'=>$row['kd_dokter'],'nm_dokter'=>$row['nm_dokter'],
        'jam_mulai'=>$mulai,'jam_selesai'=>$selesai,'current_number'=>$displayNumber,
        'current_number_numeric'=>$current?queue_number($current['no_reg']):null,'current_queue_status'=>$current?'2':null,
        'called_total'=>count($calledRows),'waiting_total'=>$waitingTotal,'next_total'=>$nextTotal,'queue_total'=>$totalQueue,
        'served_total'=>$servedTotal,'progress_percent'=>$progressPercent,'schedule_status'=>$scheduleStatus,'status_label'=>$statusLabel
    ];
}
mysqli_close($db);
echo json_encode(['ok'=>true,'date'=>$today,'hari'=>$hari,'server_time'=>$now,'updated_at'=>date('Y-m-d H:i:s'),'queue_semantics'=>['status_waiting'=>'0','status_next'=>'1','status_called'=>'2','status_passed'=>'3','current_call_rule'=>'highest_numeric_no_reg_with_status_2'],'items'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
