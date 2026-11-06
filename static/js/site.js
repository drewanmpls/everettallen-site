(function(){
try{if(!localStorage.getItem('eaSkip')&&navigator.doNotTrack!=='1'&&window.doNotTrack!=='1'&&navigator.sendBeacon)navigator.sendBeacon('/client/hit.php',new Blob([JSON.stringify({p:location.pathname,r:document.referrer,w:window.innerWidth})],{type:'text/plain'}))}catch(e){}
var b=document.querySelector('.menu-btn'),n=document.getElementById('nav');
if(b)b.addEventListener('click',function(){var o=n.classList.toggle('open');b.setAttribute('aria-expanded',o)});

function shareLinks(path,title,media){var u=location.origin+path,m=location.origin+media,t=title+' (photo by Everett Allen Photography)',eu=encodeURIComponent(u),et=encodeURIComponent(t);
 return [['Facebook','https://www.facebook.com/sharer/sharer.php?u='+eu],['X','https://twitter.com/intent/tweet?url='+eu+'&text='+et],['Bluesky','https://bsky.app/intent/compose?text='+encodeURIComponent(t+' '+u)],['Threads','https://www.threads.net/intent/post?text='+encodeURIComponent(t+' '+u)],['Pinterest','https://pinterest.com/pin/create/button/?url='+eu+'&media='+encodeURIComponent(m)+'&description='+et],['Email','mailto:?subject='+encodeURIComponent(title)+'&body='+encodeURIComponent(t+'\n'+u)]]}
function renderShare(el,path,title,media,label){
 el.innerHTML='';if(label){var l=document.createElement('span');l.className='sl';l.textContent=label;el.appendChild(l)}
 var u=location.origin+path;
 if(navigator.share){var nb=document.createElement('button');nb.type='button';nb.textContent='Share...';nb.onclick=function(){navigator.share({title:title,text:title+' (photo by Everett Allen Photography)',url:u}).catch(function(){})};el.appendChild(nb)}
 shareLinks(path,title,media).forEach(function(x){var a=document.createElement('a');a.href=x[1];a.textContent=x[0];if(x[0]!=='Email'){a.target='_blank';a.rel='noopener'}el.appendChild(a)});
 var cb=document.createElement('button');cb.type='button';cb.textContent='Copy link';cb.onclick=function(){
  function ok(){cb.textContent='Copied';setTimeout(function(){cb.textContent='Copy link'},1800)}
  if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(ok,function(){})}
  else{var t=document.createElement('textarea');t.value=u;document.body.appendChild(t);t.select();try{document.execCommand('copy');ok()}catch(e){}document.body.removeChild(t)}};
 el.appendChild(cb)}
document.querySelectorAll('.share[data-path]').forEach(function(el){renderShare(el,el.getAttribute('data-path'),el.getAttribute('data-title'),el.getAttribute('data-media'),el.getAttribute('data-label'))});
var s=document.querySelectorAll('.slide');
if(s.length>1){var i=0;setInterval(function(){s[i].classList.remove('on');i=(i+1)%s.length;s[i].classList.add('on')},5000)}
var ph=[].slice.call(document.querySelectorAll('.ph'));
if(!ph.length)return;
var lb=document.createElement('div');lb.className='lb';lb.setAttribute('role','dialog');lb.setAttribute('aria-modal','true');
lb.innerHTML='<img alt=""><button class="x" aria-label="Close">&times;</button><button class="p" aria-label="Previous">&#8249;</button><button class="n" aria-label="Next">&#8250;</button><button class="sh" type="button">Share</button><div class="lbshare share" hidden></div><span class="c"></span>';
document.body.appendChild(lb);var im=lb.querySelector('img'),c=lb.querySelector('.c'),k=0;
var sp=lb.querySelector('.lbshare'),sb=lb.querySelector('.sh');
function paintShare(){var m=/\/photos\/([^\/]+)\/([^\/]+)-l\.jpg$/.exec(ph[k].getAttribute('href'));if(!m)return;renderShare(sp,'/photo/'+m[1]+'/'+m[2]+'/',ph[k].querySelector('img').alt.replace(/, photo \d+$/,'')||'Photo','/photos/'+m[1]+'/'+m[2]+'-l.jpg','')}
sb.onclick=function(e){e.stopPropagation();if(sp.hidden){paintShare();sp.hidden=false}else sp.hidden=true};
sp.addEventListener('click',function(e){e.stopPropagation()});
function show(j){k=(j+ph.length)%ph.length;im.src=ph[k].href;im.alt=ph[k].querySelector('img').alt;c.textContent=(k+1)+' / '+ph.length;
 if(!sp.hidden)paintShare();
 var nx=new Image();nx.src=ph[(k+1)%ph.length].href}
function open(j){show(j);lb.classList.add('open');document.body.style.overflow='hidden'}
function close(){sp.hidden=true;lb.classList.remove('open');document.body.style.overflow=''}
ph.forEach(function(a,j){a.addEventListener('click',function(e){e.preventDefault();open(j)})});
lb.querySelector('.x').onclick=close;lb.querySelector('.p').onclick=function(){show(k-1)};lb.querySelector('.n').onclick=function(){show(k+1)};
lb.addEventListener('click',function(e){if(e.target===lb)close()});
document.addEventListener('keydown',function(e){if(!lb.classList.contains('open'))return;if(e.key==='Escape')close();if(e.key==='ArrowLeft')show(k-1);if(e.key==='ArrowRight')show(k+1)});
var x0=null;lb.addEventListener('touchstart',function(e){x0=e.touches[0].clientX});
lb.addEventListener('touchend',function(e){if(x0===null)return;var d=e.changedTouches[0].clientX-x0;if(Math.abs(d)>50)show(k+(d<0?1:-1));x0=null});
})();
