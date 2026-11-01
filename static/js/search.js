(function(){
  var q=document.getElementById('q'),res=document.getElementById('res'),rc=document.getElementById('rc'),data=[];
  if(!q) return;
  function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
  function norm(s){return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'')}
  function render(){
    var stop={at:1,and:1,the:1,of:1,in:1,a:1,'&':1},words=norm(q.value).split(/\s+/).filter(function(w){return w&&!stop[w]}),out=[];
    data.forEach(function(d){
      var hay=norm([d.t,d.y,d.c,d.s,d.g.join(' '),d.k].join(' '));
      var hw=hay.split(/[^a-z0-9]+/); if(words.every(function(w){return hw.some(function(h){return h.indexOf(w)===0})})) out.push(d);
    });
    rc.textContent=words.length?(out.length+' galler'+(out.length===1?'y':'ies')+' found'):(data.length+' galleries, newest first');
    res.innerHTML=out.map(function(d){
      var sub=[d.y,d.n+' photos'].concat(d.s?[d.s]:[]).join(' / ');
      return '<a class="card" href="'+d.u+'"><img loading="lazy" src="'+d.i+'" alt=""><span><b>'+esc(d.t)+'</b><small>'+esc(sub)+'</small></span></a>';
    }).join('')||'<p class="lede2">Nothing matched. Try fewer words, or browse <a href="/portraits/">Portraits</a> or <a href="/events/">Events</a>.</p>';
    try{var u=new URL(location.href); if(q.value) u.searchParams.set('q',q.value); else u.searchParams.delete('q'); history.replaceState(null,'',u)}catch(e){}
  }
  fetch('/search-index.json').then(function(r){return r.json()}).then(function(j){
    data=j; var m=/[?&]q=([^&]*)/.exec(location.search); if(m) q.value=decodeURIComponent(m[1].replace(/\+/g,' ')); render();
  });
  q.addEventListener('input',render);
  document.querySelectorAll('.chips a').forEach(function(a){a.addEventListener('click',function(ev){ev.preventDefault(); q.value=a.getAttribute('data-q'); render(); q.focus()})});
})();
