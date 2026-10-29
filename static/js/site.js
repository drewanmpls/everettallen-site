(function(){
var b=document.querySelector('.menu-btn'),n=document.getElementById('nav');
if(b)b.addEventListener('click',function(){var o=n.classList.toggle('open');b.setAttribute('aria-expanded',o)});
var s=document.querySelectorAll('.slide');
if(s.length>1){var i=0;setInterval(function(){s[i].classList.remove('on');i=(i+1)%s.length;s[i].classList.add('on')},5000)}
var ph=[].slice.call(document.querySelectorAll('.ph'));
if(!ph.length)return;
var lb=document.createElement('div');lb.className='lb';lb.setAttribute('role','dialog');lb.setAttribute('aria-modal','true');
lb.innerHTML='<img alt=""><button class="x" aria-label="Close">&times;</button><button class="p" aria-label="Previous">&#8249;</button><button class="n" aria-label="Next">&#8250;</button><span class="c"></span>';
document.body.appendChild(lb);var im=lb.querySelector('img'),c=lb.querySelector('.c'),k=0;
function show(j){k=(j+ph.length)%ph.length;im.src=ph[k].href;im.alt=ph[k].querySelector('img').alt;c.textContent=(k+1)+' / '+ph.length;
 var nx=new Image();nx.src=ph[(k+1)%ph.length].href}
function open(j){show(j);lb.classList.add('open');document.body.style.overflow='hidden'}
function close(){lb.classList.remove('open');document.body.style.overflow=''}
ph.forEach(function(a,j){a.addEventListener('click',function(e){e.preventDefault();open(j)})});
lb.querySelector('.x').onclick=close;lb.querySelector('.p').onclick=function(){show(k-1)};lb.querySelector('.n').onclick=function(){show(k+1)};
lb.addEventListener('click',function(e){if(e.target===lb)close()});
document.addEventListener('keydown',function(e){if(!lb.classList.contains('open'))return;if(e.key==='Escape')close();if(e.key==='ArrowLeft')show(k-1);if(e.key==='ArrowRight')show(k+1)});
var x0=null;lb.addEventListener('touchstart',function(e){x0=e.touches[0].clientX});
lb.addEventListener('touchend',function(e){if(x0===null)return;var d=e.changedTouches[0].clientX-x0;if(Math.abs(d)>50)show(k+(d<0?1:-1));x0=null});
})();
