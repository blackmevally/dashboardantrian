<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Antrian — RSU Permata Medika Kebumen</title>
<link rel="stylesheet" href="assets/css/display.css?v=1">
</head>
<body>
<div class="display">
  <header class="topbar">
    <div class="brand">
      <small>RSU Permata Medika Kebumen</small>
      <h1>Dashboard Antrean Pasien</h1>
      <div class="status"><span class="dot" id="liveDot"></span><span id="live">LIVE</span></div>
    </div>
    <div class="clock">
      <strong id="clock">00:00:00</strong>
      <span id="date">—</span>
    </div>
  </header>

  <main id="queueGrid" class="grid" aria-live="polite">
    <div class="empty"><div>Memuat data antrean…</div></div>
  </main>

  <footer class="footer">
    <span>Informasi nomor antrean ruang tunggu</span>
    <span id="updated">Menunggu update…</span>
  </footer>
</div>
<script src="assets/js/display.js?v=1"></script>
</body>
</html>
