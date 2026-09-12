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
<title>Dashboard Antrean</title>
<link rel="stylesheet" href="assets/css/display.css?v=10">
<link rel="stylesheet" href="assets/css/medical-icons.css?v=5">
<link rel="stylesheet" href="assets/css/premium-white.css?v=5">
<link rel="stylesheet" href="assets/css/tv-premium.css?v=2">
</head>
<body>
<div class="display">
  <header class="topbar">
    <div class="brand">
      <div class="brand-mark has-logo" id="brandMark"><img id="instLogo" src="api/logo.php?t=0" alt="Logo instansi"></div>
      <div class="brand-copy">
        <div class="eyebrow" id="instName">Memuat nama instansi…</div>
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
<script src="assets/js/display.js?v=7"></script>
<script src="assets/js/call-alert-fix.js?v=1"></script>
<script src="assets/js/page-slider-fix.js?v=1"></script>
<script>
(function(){
  'use strict';
  var logo=document.getElementById('instLogo');
  var nameEl=document.getElementById('instName');
  var mark=document.getElementById('brandMark');
  function setLogo(src){
    if(!src) return;
    logo.onload=function(){
      logo.style.display='block';
      mark.classList.add('has-logo');
    };
    logo.onerror=function(){
      logo.style.display='none';
      mark.classList.remove('has-logo');
    };
    logo.src=src;
  }
  async function loadInstitution(){
    try{
      var r=await fetch('api/institution.php?t='+Date.now(),{cache:'no-store'});
      if(!r.ok) throw new Error('HTTP '+r.status);
      var data=await r.json();
      var name=String(data.nama_instansi||'').trim();
      if(name){
        nameEl.textContent=name;
        document.title=name+' — Informasi Antrean';
      }
      if(data.logo){
        setLogo('data:'+(data.logo_mime||'image/jpeg')+';base64,'+data.logo);
      }else{
        setLogo('api/logo.php?t='+Date.now());
      }
    }catch(e){
      nameEl.textContent='RSU PERMATA MEDIKA KEBUMEN';
      setLogo('api/logo.php?t='+Date.now());
      if(window.console) console.warn('Institution API:',e);
    }
  }
  loadInstitution();
})();
</script>
</body>
</html>
