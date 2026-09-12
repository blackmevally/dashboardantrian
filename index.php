<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$institutionName = 'RSU PERMATA MEDIKA KEBUMEN';
$institutionLogo = 'api/logo.php?t=server';

try {
    require_once __DIR__ . '/config/config.php';
    $db = db_connect();
    $result = mysqli_query($db, "SELECT nama_instansi FROM setting LIMIT 1");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        if ($row && trim((string)($row['nama_instansi'] ?? '')) !== '') {
            $institutionName = trim((string)$row['nama_instansi']);
        }
        mysqli_free_result($result);
    }
    mysqli_close($db);
} catch (Throwable $e) {
    // Safe fallback when the SIMRS database is temporarily unavailable.
}

$institutionNameHtml = htmlspecialchars($institutionName, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $institutionNameHtml ?> — Informasi Antrean</title>
<link rel="stylesheet" href="assets/css/display.css?v=12">
<link rel="stylesheet" href="assets/css/medical-icons.css?v=7">
<link rel="stylesheet" href="assets/css/premium-white.css?v=7">
<link rel="stylesheet" href="assets/css/tv-premium.css?v=4">
</head>
<body>
<div class="display">
  <header class="topbar">
    <div class="brand">
      <div class="brand-mark has-logo" id="brandMark"><img id="instLogo" src="<?= htmlspecialchars($institutionLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Logo instansi"></div>
      <div class="brand-copy">
        <div class="eyebrow" id="instName"><?= $institutionNameHtml ?></div>
        <h1>Informasi Antrean Poliklinik</h1>
        <div class="tagline">CEPAT <span>•</span> RAMAH <span>•</span> PROFESIONAL</div>
      </div>
    </div>
    <div class="top-right">
      <div class="clock"><span id="date">—</span><strong id="clock">00:00:00</strong></div>
      <div class="system-status"><span class="dot" id="liveDot"></span><strong id="live">LIVE</strong><small>TERHUBUNG DENGAN SIMRS</small></div>
    </div>
  </header>
  <main id="queueGrid" class="grid" aria-live="polite"><div class="empty"><div>Memuat data antrean…</div></div></main>
  <footer class="footer">
    <div class="pager"><button id="prevPage" type="button" aria-label="Halaman sebelumnya">‹</button><strong id="pageInfo">HALAMAN 1 / 1</strong><button id="nextPage" type="button" aria-label="Halaman berikutnya">›</button><div class="progress"><span id="progressBar"></span></div></div>
    <div class="footer-message"><span class="footer-icon">●</span><strong>Silakan menunggu nomor Anda dipanggil</strong><span class="separator">|</span><span>Terima kasih atas kesabaran Anda</span></div>
    <div class="footer-update"><small>UPDATE TERAKHIR</small><strong id="updated">Menunggu update…</strong></div>
  </footer>
</div>
<script src="assets/js/display.js?v=8"></script>
<script src="assets/js/call-alert-fix.js?v=2"></script>
<script src="assets/js/page-slider-fix.js?v=2"></script>
</body>
</html>
