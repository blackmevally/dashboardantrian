(function(){
'use strict';
const POLL_MS=3000;
const state={items:new Map()};
const $=s=>document.querySelector(s);
function esc(v){return String(v??'').replace(/[&<>\"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[c]));}
function clock(){const d=new Date();$('#clock').textContent=d.toLocaleTimeString('id-ID',{hour12:false});$('#date').textContent=d.toLocaleDateString('id-ID',{weekday:'long',day:'2-digit',month:'long',year:'numeric'});}
function render(items){
 const grid=$('#queueGrid');
 if(!Array.isArray(items)||!items.length){
   grid.innerHTML='<div class="empty"><div>Tidak ada jadwal poli untuk hari ini.</div></div>';
   state.items.clear();
   return;
 }
 grid.innerHTML=items.map(item=>{
   const key=`${item.kd_poli}|${item.kd_dokter}`;
   const prev=state.items.get(key);
   const changed=prev && prev.current_number!==item.current_number;
   const no=item.current_number||'—';
   const status=item.status||'empty';
   const label=item.label||'Belum Ada Panggilan';
   const schedule=item.jam_mulai?(item.jam_mulai.slice(0,5)+(item.jam_selesai?' — '+item.jam_selesai.slice(0,5):'')):'';
   return `<article class="card status-${esc(status)}${changed?' changed':''}" data-key="${esc(key)}"><div class="poli">${esc(item.nm_poli)}</div><div class="dokter">${esc(item.nm_dokter)}</div><div class="schedule">${esc(schedule)}</div><div class="nomor${item.current_number?'':' waiting'}">${esc(no)}</div><div class="label">${esc(label)}</div></article>`;
 });
 state.items.clear();items.forEach(item=>state.items.set(`${item.kd_poli}|${item.kd_dokter}`,item));
}
async function load(){
 try{
   const r=await fetch('api/queue.php',{cache:'no-store'});
   if(!r.ok)throw new Error('HTTP '+r.status);
   const data=await r.json();
   render(data.items);
   $('#updated').textContent='Update '+new Date(data.updated_at.replace(' ','T')).toLocaleTimeString('id-ID',{hour12:false});
   $('#live').textContent='LIVE';
   $('#liveDot').classList.remove('offline');
 }catch(e){
   $('#live').textContent='OFFLINE';
   $('#liveDot').classList.add('offline');
 }
 finally{setTimeout(load,POLL_MS)}
}
setInterval(clock,1000);clock();load();
})();
