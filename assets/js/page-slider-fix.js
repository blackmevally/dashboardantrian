(function(){'use strict';
const PAGE_INTERVAL=12000;
let timer=null;
function start(){
  if(timer)clearInterval(timer);
  timer=setInterval(function(){
    const next=document.getElementById('nextPage');
    const info=document.getElementById('pageInfo');
    if(!next||next.disabled)return;
    const text=info?info.textContent:'';
    const match=text.match(/HALAMAN\s+(\d+)\s*\/\s*(\d+)/i);
    if(match&&Number(match[2])>1)next.click();
  },PAGE_INTERVAL);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();
