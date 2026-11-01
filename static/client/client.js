(function(){
var E=window.EA, imgs=E.images, cur=-1, filter='all';
var $=function(id){return document.getElementById(id)};
function toast(m){var t=$('toast');t.textContent=m;t.hidden=false;clearTimeout(toast.t);toast.t=setTimeout(function(){t.hidden=true},3500)}
function api(a,data,method){
  var o={method:method||'POST',headers:{'X-CSRF':E.csrf}};
  var url='/client/api.php?a='+a+'&t='+E.token;
  if(!method||method==='POST'){var f=new URLSearchParams(data||{});f.set('t',E.token);o.body=f}
  else if(data){Object.keys(data).forEach(function(k){url+='&'+k+'='+encodeURIComponent(data[k])})}
  return fetch(url,o).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.error||'Something went wrong');return j})});
}
function favCount(){return imgs.filter(function(i){return i.fav}).length}
function paint(){
  $('cFav').textContent=favCount();
  imgs.forEach(function(im){
    var fig=document.querySelector('.ph[data-id="'+im.id+'"]');if(!fig)return;
    var b=fig.querySelector('.heart');b.setAttribute('aria-pressed',im.fav);b.innerHTML=im.fav?'&#9829;':'&#9825;';
    fig.hidden=(filter==='fav'&&!im.fav);
  });
  if(cur>=0){var h=$('lbh');h.setAttribute('aria-pressed',imgs[cur].fav);h.innerHTML=imgs[cur].fav?'&#9829;':'&#9825;'}
}
function toggleFav(id){
  var im=imgs.filter(function(x){return x.id===id})[0];if(!im)return;
  im.fav=!im.fav;paint();
  api('fav',{i:id,on:im.fav?1:0}).catch(function(e){im.fav=!im.fav;paint();toast(e.message)});
}
document.getElementById('grid').addEventListener('click',function(e){
  var hb=e.target.closest('.heart');if(hb){e.preventDefault();toggleFav(+hb.dataset.id);return}
  var a=e.target.closest('a[data-i]');if(a){e.preventDefault();open(+a.dataset.i)}
});
document.querySelectorAll('.tab').forEach(function(b){b.addEventListener('click',function(){
  filter=b.dataset.f;document.querySelectorAll('.tab').forEach(function(x){x.classList.toggle('on',x===b)});paint();
})});
function who(){try{return localStorage.getItem('ea_name')||E.client||''}catch(_){return E.client||''}}
function pkgText(){return E.included>0?('Package: '+E.used+' of '+E.included+' included photos used'):''}
function paintPkg(){var p=$('pkg');if(p)p.textContent=pkgText()}
function mailVal(){try{return localStorage.getItem('ea_email')||''}catch(_){return ''}}
function row(b,label,sub){var r=document.createElement('div');r.className='tier';var t=document.createElement('div');t.innerHTML='<b></b><small></small>';t.firstChild.textContent=label;t.lastChild.textContent=sub;r.appendChild(t);b.appendChild(r);return r}
function renderBuy(im){
  var b=$('lbbuy');b.innerHTML='';
  // package control
  if(E.included>0){
    if(im.claimed){
      var c=document.createElement('div');c.className='inc';c.textContent='Included in your package';b.appendChild(c);
      if(im.canUnclaim){var u=document.createElement('button');u.className='link';u.textContent='Remove from my package';u.onclick=function(){api('unclaim',{i:im.id}).then(function(j){im.claimed=false;E.used=j.used;im.dlFull=im.pf<=0;im.dlWeb=im.dlFull||im.pw<=0;afterChange(im)}).catch(function(e){toast(e.message)})};b.appendChild(u)}
    }else if(!im.dlFull){
      var left=E.included-E.used;
      var cb=document.createElement('button');cb.className='btn';cb.textContent=left>0?('Add to my package ('+left+' left)'):'Package is full';cb.disabled=left<=0;
      cb.onclick=function(){cb.disabled=true;api('claim',{i:im.id}).then(function(j){im.claimed=true;im.canUnclaim=true;im.dlFull=true;im.dlWeb=true;E.used=j.used;afterChange(im)}).catch(function(e){toast(e.message);cb.disabled=false})};
      b.appendChild(cb);
    }
  }
  var em=null;
  function sizeRow(label,sub,allowed,priceText,tier,dlSize){
    var r=row(b,label,sub);
    if(allowed){var a=document.createElement('a');a.className='btn sm';a.href='/client/dl.php?t='+E.token+'&i='+im.id+'&size='+dlSize;a.textContent='Download';r.appendChild(a)}
    else{
      var p=document.createElement('span');p.className='price';p.textContent=priceText;r.appendChild(p);
      var bt=document.createElement('button');bt.className='btn sm';bt.textContent=E.payments?'Buy':'Request';
      bt.onclick=function(){
        if(!em||!em.value){toast('Enter your email below first.');if(em)em.focus();return}
        try{localStorage.setItem('ea_email',em.value)}catch(_){}
        bt.disabled=true;
        api('request',{i:im.id,email:em.value,tier:tier}).then(function(j){if(j.checkout){location.href=j.checkout}else{toast(j.message);bt.textContent='Sent'}}).catch(function(e){toast(e.message);bt.disabled=false});
      };
      r.appendChild(bt);
    }
  }
  sizeRow('Web size','For social sharing (2048 px)',im.dlWeb,im.pwText,'web','web');
  sizeRow('Full resolution','For printing (original size)',im.dlFull,im.pfText,'full','full');
  if(!(im.dlWeb&&im.dlFull)){em=document.createElement('input');em.type='email';em.placeholder='Your email (so Everett can reply)';em.value=mailVal();b.appendChild(em)}
}
function afterChange(im){
  var tag=document.querySelector('.ph[data-id="'+im.id+'"] .tag');if(tag)tag.textContent=im.claimed?'Included':im.pfText;
  paintPkg();renderBuy(im);
}
function fmt(ts){return new Date(ts*1000).toLocaleDateString(undefined,{month:'short',day:'numeric'})}
function loadComments(id){
  var box=$('lbc');box.textContent='';
  api('comments',{i:id},'GET').then(function(j){
    box.textContent='';
    if(!j.comments.length){var e=document.createElement('div');e.style.color='#888';e.textContent='No comments yet.';box.appendChild(e)}
    j.comments.forEach(function(c){
      var d=document.createElement('div');d.className='cmt'+(+c.from_owner?' me':'');
      var b=document.createElement('b');b.textContent=(+c.from_owner?'Everett':c.author)+' / '+fmt(c.created);
      var t=document.createElement('span');t.textContent=c.body;d.appendChild(b);d.appendChild(t);box.appendChild(d);
    });
  }).catch(function(){});
}
function show(){
  var im=imgs[cur];$('lbimg').src='/client/img.php?t='+E.token+'&i='+im.id+'&s=display';
  $('lbt').textContent=im.label||('Photo '+(cur+1));renderBuy(im);loadComments(im.id);paint();
  location.hash=im.id;
}
function open(i){cur=i;$('lb').hidden=false;document.body.style.overflow='hidden';$('lba').value=who();show()}
function close(){$('lb').hidden=true;document.body.style.overflow='';cur=-1;history.replaceState(null,'',location.pathname+location.search)}
function step(d){var n=cur,t=0;do{n=(n+d+imgs.length)%imgs.length;t++}while(filter==='fav'&&!imgs[n].fav&&t<imgs.length);cur=n;show()}
$('lbx').onclick=close;$('lbp').onclick=function(){step(-1)};$('lbn').onclick=function(){step(1)};
$('lbh').onclick=function(){toggleFav(imgs[cur].id)};
document.addEventListener('keydown',function(e){
  if($('lb').hidden)return;
  if(e.target.tagName==='INPUT'||e.target.tagName==='TEXTAREA'){if(e.key==='Escape')e.target.blur();return}
  if(e.key==='Escape')close();if(e.key==='ArrowLeft')step(-1);if(e.key==='ArrowRight')step(1);
});
var tx=null;$('lb').addEventListener('touchstart',function(e){tx=e.touches[0].clientX},{passive:true});
$('lb').addEventListener('touchend',function(e){if(tx===null||e.target.closest('.side'))return;var dx=e.changedTouches[0].clientX-tx;if(Math.abs(dx)>60)step(dx<0?1:-1);tx=null});
$('lbf').addEventListener('submit',function(e){
  e.preventDefault();var im=imgs[cur],body=$('lbb').value.trim();if(!body)return;
  try{localStorage.setItem('ea_name',$('lba').value)}catch(_){}
  api('comment',{i:im.id,body:body,author:$('lba').value}).then(function(){$('lbb').value='';im.n++;loadComments(im.id);toast('Comment posted')}).catch(function(e){toast(e.message)});
});
$('done').onclick=function(){
  var n=favCount();if(!n){toast('Tap the heart on a few photos first.');return}
  if(!confirm('Send your '+n+' favorite'+(n===1?'':'s')+' to Everett?'))return;
  api('done',{author:who()}).then(function(){toast('Sent. Thank you!')}).catch(function(e){toast(e.message)});
};
imgs.forEach(function(){});
paint();paintPkg();
var m=location.hash.match(/^#(\d+)$/);if(m){var ix=imgs.findIndex(function(x){return x.id===+m[1]});if(ix>=0)open(ix)}
})();
