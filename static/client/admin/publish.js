// Admin > Publish photos: drop or choose JPEGs, upload them one by one (pubfile), save the gallery (pubdone),
// then watch the public gallery until the new photos are live. Values from PHP come from data- attributes on the form.
(function(){
var $=function(i){return document.getElementById(i)},pf=$('pf');if(!pf)return;
var CSRF=pf.getAttribute('data-csrf'),LIMIT=+pf.getAttribute('data-limit'),chosen=[];
pf.addEventListener('submit',function(e){e.preventDefault()});   // the button does the work; Enter in a field must not post the form
$('pdate').value=new Date().toISOString().slice(0,10);
function slugify(t){return t.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'')}
var slugEdited=false;function auto(){if(slugEdited)return;var t=slugify($('ptitle').value),d=$('pdate').value,y=d.slice(0,4);if(t.indexOf(y+'-')===0)t=t.slice(5);$('pslug').value=slugify(d+' '+t)}
$('ptitle').oninput=auto;$('pdate').oninput=auto;$('pslug').oninput=function(){slugEdited=true};
document.querySelectorAll('input[name=mode]').forEach(function(r){r.onchange=function(){var o=document.querySelector('input[name=mode]:checked').value==='old';$('newbox').hidden=o;if($('oldbox'))$('oldbox').hidden=!o}});

// choosing photos: the picker (tap or click the box) or drag and drop onto it
function take(list){
  var all=[].slice.call(list||[]),jp=all.filter(function(f){return /\.jpe?g$/i.test(f.name)||f.type==='image/jpeg'});
  chosen=jp.sort(function(a,b){return a.name.localeCompare(b.name,undefined,{numeric:true})});
  $('pchosen').textContent=chosen.length?chosen.length+' photo'+(chosen.length>1?'s':'')+' ready'+(all.length>jp.length?' ('+(all.length-jp.length)+' skipped: not JPEG)':'')+'.':(all.length?'Those are not JPEGs.':'');
}
$('pfiles').addEventListener('change',function(){take(this.files)});
var drop=$('pdrop');
['dragenter','dragover'].forEach(function(n){drop.addEventListener(n,function(e){e.preventDefault();drop.classList.add('over')})});
['dragleave','dragend'].forEach(function(n){drop.addEventListener(n,function(){drop.classList.remove('over')})});
drop.addEventListener('drop',function(e){e.preventDefault();drop.classList.remove('over');take(e.dataTransfer&&e.dataTransfer.files)});

function post(fd){return fetch('/client/admin/',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json().catch(function(){return{error:'The server returned an unexpected answer (the file may be too big).'}})})}
function row(f){   // one progress row: thumbnail, file name, status
  var li=document.createElement('li'),im=document.createElement('img'),nm=document.createElement('span'),st=document.createElement('span');
  im.alt='';im.src=URL.createObjectURL(f);im.onload=function(){URL.revokeObjectURL(im.src)};
  nm.className='nm';nm.textContent=f.name;st.className='st';st.textContent='Waiting';
  li.appendChild(im);li.appendChild(nm);li.appendChild(st);$('plist').appendChild(li);
  return function(text,kind){st.textContent=text;li.className=kind||''};
}
function watch(url,count){   // every 15 seconds for up to 6 minutes: is the gallery live with the new photo count?
  var box=$('plive'),tries=0,want=' '+count+' photos';
  box.hidden=false;box.className='msg wait';box.textContent='Waiting for the site to rebuild...';
  (function check(){
    tries++;
    fetch(url,{cache:'no-store',credentials:'omit'}).then(function(r){return r.ok?r.text():''}).catch(function(){return ''}).then(function(t){
      if(t&&t.indexOf(want)!==-1){box.className='msg good';box.innerHTML='';box.appendChild(document.createTextNode('Live now: '));var a=document.createElement('a');a.href=url;a.target='_blank';a.rel='noopener';a.textContent='open the gallery';box.appendChild(a);box.appendChild(document.createTextNode('.'));return}
      if(tries>=24){box.className='msg wait';box.textContent='Still building. Check the gallery in a few minutes.';return}
      box.textContent='Waiting for the site to rebuild... (checked '+tries+' time'+(tries>1?'s':'')+')';
      setTimeout(check,15000);
    });
  })();
}
$('pgo').onclick=async function(){
 var old=document.querySelector('input[name=mode]:checked').value==='old';
 var slug=old?$('pold').value:$('pslug').value.trim(),title=old?$('pold').options[$('pold').selectedIndex].text:$('ptitle').value.trim();
 if(!slug||(!old&&!title)){alert('Fill in the title first.');return}
 var files=chosen.slice();
 if(!files.length){alert('Choose some JPEGs first.');return}
 $('pgo').disabled=true;$('plist').innerHTML='';$('plive').hidden=true;var done=[],bad=0,set=files.map(row);
 for(var i=0;i<files.length;i++){
  var f=files[i];
  $('pstat').textContent='Uploading '+(i+1)+' of '+files.length+'... keep this page open.';
  if(f.size>LIMIT){set[i]('Too big for the server ('+Math.round(f.size/1048576)+' MB), skipped','bad');bad++;continue}
  set[i]('Uploading','busy');
  var res=null;
  for(var t=0;t<2&&!(res&&res.ok);t++){var fd=new FormData();fd.append('a','pubfile');fd.append('csrf',CSRF);fd.append('slug',slug);fd.append('title',title);fd.append('photo',f);res=await post(fd).catch(function(){return{error:'network error'}})}
  if(res&&res.ok){done.push({id:res.id,ratio:res.ratio});set[i]('Uploaded','ok')}else{set[i]((res&&res.error)||'Failed','bad');bad++}
 }
 if(!done.length){$('pstat').textContent='Nothing was uploaded.';$('pgo').disabled=false;return}
 $('pstat').textContent='Saving the gallery...';
 var payload={slug:slug,title:title,date:$('pdate').value,category:$('pcat').value,images:done};
 if(!old){var sr=$('pser').value,tg=$('ptags').value.split(/\n/).map(function(x){return x.trim()}).filter(Boolean);if(sr)payload.series=sr;if(tg.length)payload.tags=tg}
 var fd=new FormData();fd.append('a','pubdone');fd.append('csrf',CSRF);fd.append('payload',JSON.stringify(payload));
 var r=await post(fd);
 if(r&&r.ok){$('pstat').textContent='Published '+done.length+' photo'+(done.length>1?'s':'')+(bad?' ('+bad+' skipped).':'.')+(r.created?' Add a description any time under Galleries.':'');watch(r.url,r.count)}
 else{$('pstat').textContent='Photos are uploaded but the gallery was not saved: '+((r&&r.error)||'unknown error')+' You can press the button again (nothing is duplicated).';$('pgo').disabled=false}
};
})();
