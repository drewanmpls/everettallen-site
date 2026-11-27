// Admin > Publish photos: upload JPEGs one by one (pubfile), then save the gallery (pubdone).
// Values from PHP come from data- attributes on the form, so the page has no inline script.
(function(){
var $=function(i){return document.getElementById(i)},pf=$('pf');if(!pf)return;
var CSRF=pf.getAttribute('data-csrf'),LIMIT=+pf.getAttribute('data-limit');
pf.addEventListener('submit',function(e){e.preventDefault()});   // the button does the work; Enter in a field must not post the form
$('pdate').value=new Date().toISOString().slice(0,10);
function slugify(t){return t.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'')}
var slugEdited=false;function auto(){if(slugEdited)return;var t=slugify($('ptitle').value),d=$('pdate').value,y=d.slice(0,4);if(t.indexOf(y+'-')===0)t=t.slice(5);$('pslug').value=slugify(d+' '+t)}
$('ptitle').oninput=auto;$('pdate').oninput=auto;$('pslug').oninput=function(){slugEdited=true};
document.querySelectorAll('input[name=mode]').forEach(function(r){r.onchange=function(){var o=document.querySelector('input[name=mode]:checked').value==='old';$('newbox').hidden=o;if($('oldbox'))$('oldbox').hidden=!o}});
function post(fd){return fetch('/client/admin/',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json().catch(function(){return{error:'The server returned an unexpected answer (the file may be too big).'}})})}
$('pgo').onclick=async function(){
 var old=document.querySelector('input[name=mode]:checked').value==='old';
 var slug=old?$('pold').value:$('pslug').value.trim(),title=old?$('pold').options[$('pold').selectedIndex].text:$('ptitle').value.trim();
 if(!slug||(!old&&!title)){alert('Fill in the title first.');return}
 var files=[].slice.call($('pfiles').files).filter(function(f){return /\.jpe?g$/i.test(f.name)}).sort(function(a,b){return a.name.localeCompare(b.name,undefined,{numeric:true})});
 if(!files.length){alert('Choose some JPEGs first.');return}
 $('pgo').disabled=true;var list=$('plist');list.innerHTML='';var done=[],bad=0;
 for(var i=0;i<files.length;i++){
  var f=files[i],li=document.createElement('li');li.textContent=f.name+': ';list.appendChild(li);
  $('pstat').textContent='Uploading '+(i+1)+' of '+files.length+'... keep this page open.';
  if(f.size>LIMIT){li.textContent+='too big for the server ('+Math.round(f.size/1048576)+' MB), skipped';bad++;continue}
  var res=null;
  for(var t=0;t<2&&!(res&&res.ok);t++){var fd=new FormData();fd.append('a','pubfile');fd.append('csrf',CSRF);fd.append('slug',slug);fd.append('title',title);fd.append('photo',f);res=await post(fd).catch(function(){return{error:'network error'}})}
  if(res&&res.ok){done.push({id:res.id,ratio:res.ratio});li.textContent+='ok'}else{li.textContent+=(res&&res.error)||'failed';bad++}
 }
 if(!done.length){$('pstat').textContent='Nothing was uploaded.';$('pgo').disabled=false;return}
 $('pstat').textContent='Saving the gallery...';
 var fd=new FormData();fd.append('a','pubdone');fd.append('csrf',CSRF);fd.append('payload',JSON.stringify({slug:slug,title:title,date:$('pdate').value,category:$('pcat').value,images:done}));
 var r=await post(fd);
 if(r&&r.ok){$('pstat').innerHTML='Published '+done.length+' photo'+(done.length>1?'s':'')+(bad?' ('+bad+' skipped).':'.')+' The site rebuilds in a minute or two: <a href="'+r.url+'" target="_blank">open the gallery</a>.'+(r.created?' Fill in the description and series in Pages CMS.':'')}
 else{$('pstat').textContent='Photos are uploaded but the gallery was not saved: '+((r&&r.error)||'unknown error')+' You can press the button again (nothing is duplicated).';$('pgo').disabled=false}
};
})();
