(function(){
  // Portraits page: A to Z (as built) or Newest, reordering the existing cards in place (T-36)
  var box=document.querySelector('.sort'),pc=document.getElementById('pc'),so=document.getElementById('so');
  if(!box||!pc) return;
  var bs=box.querySelectorAll('button'),cards=Array.prototype.slice.call(pc.children);
  function by(k){return function(a,b){   // ties and A to Z keep the built order, so A to Z is exactly the no-JS page
    if(k==='new'){var d=b.dataset.date.localeCompare(a.dataset.date); if(d) return d;}
    return cards.indexOf(a)-cards.indexOf(b);
  }}
  function go(k){
    cards.slice().sort(by(k)).forEach(function(c){pc.appendChild(c)});
    for(var i=0;i<bs.length;i++) bs[i].setAttribute('aria-pressed',bs[i].dataset.sort===k?'true':'false');
    if(so) so.textContent=k==='new'?'Newest first':'A to Z';
  }
  for(var i=0;i<bs.length;i++) bs[i].addEventListener('click',function(){go(this.dataset.sort)});
  box.hidden=false;
})();
