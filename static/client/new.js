// New-client form (/client/new/): shows the model release when "Yes" is chosen, and the signature pad.
(function(){
var radios=document.querySelectorAll('input[name=share_pref]'),rel=document.getElementById('release');
if(!rel)return;
var cv=document.getElementById('sig'),ctx=cv.getContext('2d'),drawing=false,inked=false;
ctx.fillStyle='#fff';ctx.fillRect(0,0,cv.width,cv.height);ctx.lineWidth=3;ctx.lineCap='round';ctx.lineJoin='round';ctx.strokeStyle='#111';
function pos(e){var r=cv.getBoundingClientRect();return[(e.clientX-r.left)*cv.width/r.width,(e.clientY-r.top)*cv.height/r.height]}
cv.addEventListener('pointerdown',function(e){drawing=true;inked=true;var p=pos(e);ctx.beginPath();ctx.moveTo(p[0],p[1]);cv.setPointerCapture(e.pointerId);e.preventDefault()});
cv.addEventListener('pointermove',function(e){if(!drawing)return;var p=pos(e);ctx.lineTo(p[0],p[1]);ctx.stroke();e.preventDefault()});
['pointerup','pointercancel'].forEach(function(n){cv.addEventListener(n,function(){drawing=false})});
document.getElementById('sigclear').onclick=function(){ctx.fillStyle='#fff';ctx.fillRect(0,0,cv.width,cv.height);inked=false;document.getElementById('release_sig').value=''};
function show(){var y=false;radios.forEach(function(x){if(x.checked&&x.value==='Yes')y=true});rel.hidden=!y}
radios.forEach(function(x){x.addEventListener('change',show)});show();
document.getElementById('intake').addEventListener('submit',function(e){
  var yes=false;radios.forEach(function(x){if(x.checked&&x.value==='Yes')yes=true});
  if(yes){if(!inked){e.preventDefault();alert('Please sign in the signature box first.');return}
    document.getElementById('release_sig').value=cv.toDataURL('image/jpeg',0.85)}
});
})();
