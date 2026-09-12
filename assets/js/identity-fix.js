(function(){
  'use strict';

  function applyBranding(data){
    var name = String((data && (data.nama_instansi || data.institution_name || data.name)) || '').trim();
    if(name){
      document.querySelectorAll('#instName, .eyebrow').forEach(function(el){
        if(el.closest('.brand-copy')) el.textContent = name;
      });
      document.title = name + ' — Informasi Antrean';
    }

    var mark = document.getElementById('brandMark');
    if(!mark) return;
    var img = document.getElementById('instLogo');
    if(!img){
      mark.classList.add('has-logo');
      mark.innerHTML = '<img id="instLogo" alt="Logo instansi">';
      img = document.getElementById('instLogo');
    }
    img.src = 'api/logo.php?t=' + Date.now();
    img.alt = name ? 'Logo ' + name : 'Logo instansi';
    img.style.display = 'block';
    mark.classList.add('has-logo');
  }

  fetch('api/institution.php?t=' + Date.now(), {cache:'no-store'})
    .then(function(response){
      if(!response.ok) throw new Error('HTTP ' + response.status);
      return response.json();
    })
    .then(applyBranding)
    .catch(function(){
      var img = document.getElementById('instLogo');
      if(img) img.src = 'api/logo.php?t=' + Date.now();
    });
})();
