// Admin shell: appearance (Auto, Light, Dark), the menu panel on tablets and phones, and the "skip my own visits" flag for stats.
// Loaded in <head> without defer so the saved appearance applies before the page paints.
(function () {
  var root = document.documentElement;
  root.classList.add('js');
  function getMode() { try { return localStorage.getItem('eaTheme') || 'auto'; } catch (e) { return 'auto'; } }
  function applyMode(m) { if (m === 'light' || m === 'dark') root.setAttribute('data-mode', m); else root.removeAttribute('data-mode'); }
  applyMode(getMode());
  try { localStorage.setItem('eaSkip', '1'); } catch (e) {}   // your own visits are not counted in Stats

  document.addEventListener('DOMContentLoaded', function () {
    var sels = document.querySelectorAll('#eaTheme,select[data-theme]');   // the menu one, and the one on Settings > Account
    sels.forEach(function (sel) {
      sel.value = getMode();
      sel.addEventListener('change', function () {
        try { localStorage.setItem('eaTheme', sel.value); } catch (e) {}
        applyMode(sel.value); sels.forEach(function (o) { o.value = sel.value; });
      });
    });
    document.querySelectorAll('[data-show]').forEach(function (b) {   // Show / Hide on secret fields
      var inp = b.previousElementSibling;
      b.addEventListener('click', function () {
        var show = inp.type === 'password'; inp.type = show ? 'text' : 'password';
        b.textContent = show ? 'Hide' : 'Show'; b.setAttribute('aria-pressed', show ? 'true' : 'false');
      });
    });
    var side = document.getElementById('side');
    if (!side) return;
    var openers = document.querySelectorAll('[data-menu-open]');
    var closer = side.querySelector('.mnuclose');
    var last = null;
    function setOpen(open) {
      root.classList.toggle('menuopen', open);
      openers.forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
      if (open) { side.removeAttribute('inert'); (closer || side).focus(); }
      else { if (window.matchMedia('(max-width:1023px)').matches) side.setAttribute('inert', ''); if (last) last.focus(); }
    }
    function sync() {   // the panel is only hidden below 1024px; keep it reachable on wider screens
      if (window.matchMedia('(max-width:1023px)').matches) { if (!root.classList.contains('menuopen')) side.setAttribute('inert', ''); }
      else { side.removeAttribute('inert'); root.classList.remove('menuopen'); }
    }
    openers.forEach(function (b) { b.addEventListener('click', function () { last = b; setOpen(!root.classList.contains('menuopen')); }); });
    if (closer) closer.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && root.classList.contains('menuopen')) setOpen(false); });
    document.addEventListener('click', function (e) { if (root.classList.contains('menuopen') && !side.contains(e.target) && !e.target.closest('[data-menu-open]')) setOpen(false); });
    window.addEventListener('resize', sync); sync();
  });

  // Page features. Values from PHP arrive in data- attributes, so no page needs inline script (T-21, for the CSP in T-22).
  document.addEventListener('DOMContentLoaded', function () {
    // Forms that delete or reset ask first: <form data-confirm="Question?">
    document.addEventListener('submit', function (e) {
      var q = e.target.getAttribute && e.target.getAttribute('data-confirm');
      if (q && !window.confirm(q)) e.preventDefault();
    });
    // Copy buttons: data-copy="#id" copies that element's text; data-copy-text="..." copies the value itself
    document.querySelectorAll('[data-copy],[data-copy-text]').forEach(function (b) {
      b.addEventListener('click', function () {
        var sel = b.getAttribute('data-copy'), el = sel ? document.querySelector(sel) : null;
        var text = el ? el.textContent : b.getAttribute('data-copy-text');
        navigator.clipboard.writeText(text).then(function () { b.textContent = 'Copied'; });
      });
    });

    // Find box over a table: <input data-filter="#table"> hides rows whose data-q does not contain the words typed
    document.querySelectorAll('input[data-filter]').forEach(function (q) {
      var tbl = document.querySelector(q.getAttribute('data-filter')); if (!tbl) return;
      var rows = [].slice.call(tbl.querySelectorAll('tr[data-q]')), count = document.getElementById('pgn'), none = document.getElementById('pgnone');
      q.addEventListener('input', function () {
        var words = q.value.toLowerCase().split(/\s+/).filter(Boolean), n = 0;
        rows.forEach(function (r) { var hit = words.every(function (w) { return r.getAttribute('data-q').indexOf(w) !== -1; }); r.hidden = !hit; if (hit) n++; });
        if (count) count.textContent = n + (n === 1 ? ' gallery' : ' galleries');
        if (none) none.hidden = n > 0;
      });
    });

    // Client gallery: per-photo prices save as you change them; the upload box sends photos one at a time
    var files = document.getElementById('files');
    if (files) {
      var CSRF = files.getAttribute('data-csrf'), GID = files.getAttribute('data-gid'), BASE = '/client/admin/';
      document.querySelectorAll('.pr').forEach(function (el) {
        el.addEventListener('change', function () {
          var f = new URLSearchParams({ a: 'price', image: el.dataset.img, tier: el.dataset.tier, price: el.value, csrf: CSRF });
          fetch(BASE, { method: 'POST', body: f }).then(function (r) { el.classList.remove('saved', 'failed'); el.classList.add(r.ok ? 'saved' : 'failed'); });
        });
      });
      files.addEventListener('change', async function (e) {
        var fs = Array.from(e.target.files).sort(function (a, b) { return a.name.localeCompare(b.name, undefined, { numeric: true }); });
        var p = document.getElementById('prog'), bad = [];
        for (var i = 0; i < fs.length; i++) {
          p.textContent = 'Uploading ' + (i + 1) + ' of ' + fs.length + ': ' + fs[i].name;
          var fd = new FormData(); fd.append('a', 'upload'); fd.append('id', GID); fd.append('csrf', CSRF); fd.append('photo', fs[i]);
          try { var r = await fetch(BASE, { method: 'POST', body: fd }); var j = await r.json(); if (!r.ok) bad.push(fs[i].name + ': ' + (j.error || 'failed')); } catch (_) { bad.push(fs[i].name + ': network error'); }
        }
        p.textContent = bad.length ? ('Done with problems: ' + bad.join(' | ')) : 'All uploaded.';
        if (!bad.length) location.reload();
      });
    }

    // Client record: "Use camera" for the model photo and the ID photo on the release form
    (function () {
    var dlg=document.getElementById('camdlg'); if (!dlg) return;
    var vid=document.getElementById('camvid'),err=document.getElementById('camerr'),title=document.getElementById('camtitle'),stream=null,field=null;
    function stop(){ if(stream){stream.getTracks().forEach(function(t){t.stop()});stream=null} vid.srcObject=null }
    function close(){ stop(); if(dlg.open) dlg.close() }
    document.querySelectorAll('[data-cam]').forEach(function(btn){
      btn.addEventListener('click',function(){
        field=btn.getAttribute('data-cam'); err.textContent='';
        title.textContent=field==='idphoto'?'Photo of the ID (fill the frame, check it is readable)':'Photo of the model';
        if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){ alert('This browser cannot use the camera here. Use the file box instead.'); return }
        dlg.showModal();
        navigator.mediaDevices.getUserMedia({video:{facingMode:field==='idphoto'?'environment':'user',width:{ideal:1920},height:{ideal:1080}},audio:false}).then(function(st){ stream=st; vid.srcObject=st }).catch(function(e){ err.textContent='Could not open the camera ('+(e&&e.name||'error')+'). Allow camera access for this site in the browser, then try again.' });
      });
    });
    document.getElementById('camcancel').addEventListener('click',close);
    dlg.addEventListener('cancel',function(){ stop() });
    document.getElementById('camsnap').addEventListener('click',function(){
      if(!stream||!vid.videoWidth) return;
      var cv=document.createElement('canvas'); cv.width=vid.videoWidth; cv.height=vid.videoHeight; cv.getContext('2d').drawImage(vid,0,0);
      cv.toBlob(function(b){
        if(!b){ err.textContent='Could not capture. Try again.'; return }
        var f=new File([b],field+'-camera.jpg',{type:'image/jpeg'}),dt=new DataTransfer(); dt.items.add(f);
        document.querySelector('input[name="'+field+'"]').files=dt.files;
        var n=document.querySelector('[data-note="'+field+'"]'); if(n) n.textContent='Photo ready. Click Update release PDF to save it.';
        close();
      },'image/jpeg',0.9);
    });
    })();
  });
})();
