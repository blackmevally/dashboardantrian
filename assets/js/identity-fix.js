(function(){
  'use strict';

  var logoUrl = 'api/logo.php?setting_logo=' + Date.now();
  var fallbackName = 'RSU PERMATA MEDIKA KEBUMEN';

  function applyBranding(data){
    var name = String((data && (data.nama_instansi || data.institution_name || data.name)) || '').trim();
    if(!name) name = fallbackName;

    document.querySelectorAll('#instName, .brand-copy .eyebrow').forEach(function(el){
      el.textContent = name;
    });
    document.title = name + ' — Informasi Antrean';

    var mark = document.getElementById('brandMark');
    if(!mark){
      mark = document.querySelector('.brand-mark');
      if(mark) mark.id = 'brandMark';
    }
    if(!mark) return;

    mark.classList.add('has-logo');
    var img = document.getElementById('instLogo');
    if(!img){
      img = document.createElement('img');
      img.id = 'instLogo';
      mark.replaceChildren(img);
    }
    img.src = logoUrl;
    img.alt = 'Logo ' + name;
    img.style.display = 'block';
    img.style.visibility = 'visible';
    img.style.opacity = '1';
    img.style.width = '100%';
    img.style.height = '100%';
    img.style.objectFit = 'contain';
  }

  function protectBranding(){
    var current = document.getElementById('instLogo');
    if(current && current.src.indexOf('/api/logo.php') === -1){
      current.src = logoUrl;
    }
    var mark = document.getElementById('brandMark');
    if(mark && !document.getElementById('instLogo')){
      applyBranding({});
    }
  }

  fetch('api/institution.php?t=' + Date.now(), {cache:'no-store'})
    .then(function(response){
      if(!response.ok) throw new Error('HTTP ' + response.status);
      return response.json();
    })
    .then(applyBranding)
    .catch(function(){ applyBranding({}); });

  window.setTimeout(protectBranding, 50);
  window.setTimeout(protectBranding, 300);
  window.setTimeout(protectBranding, 1000);
})();
