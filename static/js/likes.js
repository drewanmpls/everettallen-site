(function(){
var KEY='eaLikes',counts={},slug='',tiles=[],lb=null,lbBtn=null,pageBtn=null,pimg=null;
function load(){try{var a=JSON.parse(localStorage.getItem(KEY)||'[]');return Array.isArray(a)?a.filter(function(x){return /^[a-z0-9-]+\/\d{3,6}$/.test(x)}).slice(-3000):[]}catch(e){return[]}}
function save(a){try{localStorage.setItem(KEY,JSON.stringify(a))}catch(e){}}
var mine=load();
function keyFromUrl(u){var m=/\/photos\/([a-z0-9-]+)\/(\d{3,6})-[tl]\.jpg/.exec(u||'');return m?m[1]+'/'+m[2]:''}
function has(k){return mine.indexOf(k)>-1}
function heart(f){return '<svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><path d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 2.7 4.5 6.4 4.5c2.1 0 3.8 1.2 5.6 3.3 1.8-2.1 3.5-3.3 5.6-3.3 3.7 0 5.5 3.9 4 7.3C19.5 16.4 12 21 12 21z" fill="'+(f?'currentColor':'none')+'" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>'}
function btnHtml(k){var n=counts[k]||0;return heart(has(k))+'<span class="n">'+(n>0?n:'')+'</span>'}
function paint(){
 tiles.forEach(function(a){var k=keyFromUrl(a.getAttribute('href')),n=counts[k]||0,b=a.querySelector('.lkb');
  if(n>0){if(!b){b=document.createElement('span');b.className='lkb';a.appendChild(b)}b.innerHTML=heart(has(k))+n;b.classList.toggle('mine',has(k))}else if(b)b.remove()});
 if(lbBtn){var img=lb.querySelector('img'),k=keyFromUrl(img&&img.getAttribute('src'));lbBtn.hidden=!k;if(k){lbBtn.innerHTML=btnHtml(k);lbBtn.classList.toggle('on',has(k));lbBtn.setAttribute('aria-pressed',has(k))}}
 if(pageBtn){var k2=keyFromUrl(pimg.getAttribute('src'));pageBtn.innerHTML=btnHtml(k2)+'<span class="t">'+(has(k2)?'Liked':'Like this photo')+'</span>';pageBtn.classList.toggle('on',has(k2));pageBtn.setAttribute('aria-pressed',has(k2))}
}
function toggle(k){
 if(!k)return;var on=!has(k),parts=k.split('/');
 if(on)mine.push(k);else mine=mine.filter(function(x){return x!==k});
 counts[k]=Math.max(0,(counts[k]||0)+(on?1:-1));save(mine);paint();
 var fd=new FormData();fd.append('a','like');fd.append('g',parts[0]);fd.append('id',parts[1]);fd.append('on',on?'1':'');
 fetch('/client/likes.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  if(j&&j.ok){counts[k]=j.n;paint()}else{throw 0}
 }).catch(function(){ // undo
  if(on)mine=mine.filter(function(x){return x!==k});else mine.push(k);counts[k]=Math.max(0,(counts[k]||0)+(on?-1:1));save(mine);paint()});
}
document.addEventListener('DOMContentLoaded',function(){
 tiles=[].slice.call(document.querySelectorAll('.ph'));pimg=document.querySelector('.pimg');lb=document.querySelector('.lb');
 var first=tiles.length?keyFromUrl(tiles[0].getAttribute('href')):(pimg?keyFromUrl(pimg.getAttribute('src')):'');
 if(!first)return;slug=first.split('/')[0];
 if(lb){lbBtn=document.createElement('button');lbBtn.type='button';lbBtn.className='lblike';lbBtn.setAttribute('aria-label','Like this photo');
  lbBtn.onclick=function(e){e.stopPropagation();toggle(keyFromUrl(lb.querySelector('img').getAttribute('src')))};lb.appendChild(lbBtn);
  new MutationObserver(paint).observe(lb.querySelector('img'),{attributes:true,attributeFilter:['src']})}
 if(pimg){pageBtn=document.createElement('button');pageBtn.type='button';pageBtn.className='lkpage';
  pageBtn.onclick=function(){toggle(keyFromUrl(pimg.getAttribute('src')))};pimg.insertAdjacentElement('afterend',pageBtn)}
 paint();
 fetch('/client/likes.php?g='+encodeURIComponent(slug),{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  if(j&&j.counts){var c={};Object.keys(j.counts).forEach(function(id){c[slug+'/'+id]=j.counts[id]});counts=c;paint()}}).catch(function(){});
});
})();
