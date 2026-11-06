// Buying photos from the public galleries: "Add to order" in the lightbox / on photo pages, a small cart, one checkout.
// Only shows up when selling is switched on (Admin > Settings > Payments).
(function(){
var KEY='eaCart',cfg=null,bar=null,dlg=null,addBtns=[],done=false;
function load(){try{var a=JSON.parse(localStorage.getItem(KEY)||'[]');return Array.isArray(a)?a.filter(function(x){return /^[a-z0-9-]+\/\d{3,6}$/.test(x)}).slice(0,30):[]}catch(e){return[]}}
function save(a){try{localStorage.setItem(KEY,JSON.stringify(a))}catch(e){}}
var cart=load();
function has(k){return cart.indexOf(k)>-1}
function toggle(k){var i=cart.indexOf(k);if(i>-1)cart.splice(i,1);else if(cart.length<30)cart.push(k);save(cart);paint()}
function keyFromUrl(u){var m=/\/photos\/([a-z0-9-]+)\/(\d{3,6})-[tl]\.jpg/.exec(u||'');return m?m[1]+'/'+m[2]:''}
function money(c){return '$'+(c%100===0?(c/100):(c/100).toFixed(2))}
function el(t,c,h){var e=document.createElement(t);if(c)e.className=c;if(h!=null)e.innerHTML=h;return e}

function paint(){
 addBtns.forEach(function(b){var k=b.getKey();if(!k)return;b.el.textContent=has(k)?'In your order (remove)':'Add to order';b.el.classList.toggle('on',has(k))});
 if(!bar)return;
 bar.hidden=!cart.length;
 bar.querySelector('.cnt').textContent=cart.length+(cart.length===1?' photo':' photos')+' in your order';
 if(dlg&&!dlg.hidden)paintDlg();
}

function makeAddBtn(getKey,cls){var b=el('button','buy '+(cls||''),'Add to order');b.type='button';b.onclick=function(e){e.stopPropagation();var k=getKey();if(k)toggle(k)};addBtns.push({el:b,getKey:getKey});return b}

function build(){
 bar=el('div','cartbar','<span class="cnt"></span><button type="button" class="btn go">Review &amp; check out</button>');bar.hidden=true;
 bar.querySelector('.go').onclick=openDlg;document.body.appendChild(bar);
 // lightbox button
 var lb=document.querySelector('.lb');
 if(lb){var img=lb.querySelector('img');lb.appendChild(makeAddBtn(function(){return keyFromUrl(img.getAttribute('src'))},'lbbuy'));
  new MutationObserver(paint).observe(img,{attributes:true,attributeFilter:['src']})}
 // photo page button
 var pi=document.querySelector('.pimg');
 if(pi){var pb=makeAddBtn(function(){return keyFromUrl(pi.getAttribute('src'))},'pgbuy');var host=document.querySelector('.pnav');if(host&&host.parentNode)host.parentNode.insertBefore(el('p','buyrow'),host.nextSibling).appendChild(pb)}
 paint();
}

function openDlg(){
 if(!dlg){dlg=el('div','cdlg');dlg.setAttribute('role','dialog');dlg.setAttribute('aria-modal','true');dlg.hidden=true;
  dlg.innerHTML='<div class="cbox"><button type="button" class="cx" aria-label="Close">&times;</button><h2>Your order</h2><div class="cl"></div><fieldset class="ct"><legend>Size</legend></fieldset><label class="ce">Email for your download link<input type="email" autocomplete="email" placeholder="you@example.com"></label><p class="cs"></p><p class="cm" role="status"></p><button type="button" class="btn pay">Pay securely</button><p class="cn">You pay on Stripe\'s secure page. Right after, you get a private link to download your photos, and the same link works any time later. Photos are for personal use; see the usage note on each gallery, or <a href="/contact/">get in touch</a> for other uses.</p></div>';
  document.body.appendChild(dlg);
  dlg.querySelector('.cx').onclick=function(){dlg.hidden=true;document.body.style.overflow=''};
  dlg.addEventListener('click',function(e){if(e.target===dlg){dlg.hidden=true;document.body.style.overflow=''}});
  dlg.querySelector('.pay').onclick=pay;
  var ct=dlg.querySelector('.ct');
  [['web','Web size','2048 pixels on the long side, great for social media and sharing',cfg.web],['full','Full size','The original full-resolution file, for printing',cfg.full]].forEach(function(o,i){
   if(!(o[3]>0))return;var l=el('label','opt','<input type="radio" name="sz" value="'+o[0]+'"><span><b>'+o[1]+'</b> <em>'+money(o[3])+' each</em><small>'+o[2]+'</small></span>');ct.appendChild(l);l.querySelector('input').onchange=paintDlg});
  var first=ct.querySelector('input');if(first)first.checked=true;
  var rs=ct.querySelectorAll('input');if(rs.length>1)rs[rs.length-1].checked=true}
 done=false;dlg.querySelector('.pay').hidden=false;dlg.querySelector('.cm').textContent='';dlg.hidden=false;document.body.style.overflow='hidden';paintDlg()}
function tier(){var r=dlg.querySelector('input[name=sz]:checked');return r?r.value:''}
function paintDlg(){
 var cl=dlg.querySelector('.cl');cl.innerHTML='';
 if(done)return;
 if(!cart.length){dlg.hidden=true;document.body.style.overflow='';return}
 cart.forEach(function(k){var p=k.split('/');var d=el('div','ci','<img alt="" src="/photos/'+p[0]+'/'+p[1]+'-t.jpg"><span>Photo '+parseInt(p[1],10)+'</span>');var x=el('button','rm','Remove');x.type='button';x.onclick=function(){toggle(k)};d.appendChild(x);cl.appendChild(d)});
 var t=tier(),per=t==='web'?cfg.web:cfg.full;dlg.querySelector('.cs').innerHTML='<b>Total: '+money(per*cart.length)+'</b> ('+cart.length+' x '+money(per)+')'}
function pay(){
 var m=dlg.querySelector('.cm'),btn=dlg.querySelector('.pay'),em=dlg.querySelector('input[type=email]').value.trim();
 if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)){m.textContent='Please enter a valid email so I can send your download link.';return}
 btn.disabled=true;m.textContent='One moment...';
 var fd=new URLSearchParams();fd.append('a','checkout');fd.append('email',em);fd.append('tier',tier());cart.forEach(function(k){fd.append('items[]',k)});
 fetch('/client/shop.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  if(j.checkout){cart=[];save(cart);location.href=j.checkout;return}
  btn.disabled=false;
  if(j.ok){done=true;cart=[];save(cart);paint();dlg.querySelector('.cl').innerHTML='';m.style.color='#0a6b2d';m.textContent=j.message||'Sent.';btn.hidden=true;return}
  m.textContent=j.error||'Something went wrong. Please try again.'
 }).catch(function(){btn.disabled=false;m.textContent='Could not reach the server. Please try again.'})}

window.addEventListener('load',function(){
 if(!document.querySelector('.ph,.pimg'))return;
 fetch('/client/shop.php?a=info',{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){if(j&&j.enabled){cfg=j;build()}}).catch(function(){});
 document.addEventListener('keydown',function(e){if(e.key==='Escape'&&dlg&&!dlg.hidden){dlg.hidden=true;document.body.style.overflow=''}});
});
})();
