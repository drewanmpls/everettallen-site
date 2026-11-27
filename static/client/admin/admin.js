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

    // Gallery editor: when the date changes, the year follows it unless you typed a different year yourself
    document.querySelectorAll('input[data-year]').forEach(function (d) {
      var y = document.querySelector(d.getAttribute('data-year')); if (!y) return;
      var last = d.value.slice(0, 4);
      d.addEventListener('change', function () { if (y.value === '' || y.value === last) y.value = d.value.slice(0, 4); last = d.value.slice(0, 4); });
    });

    // Gallery editor, Photos: drag or Earlier/Later to reorder, then Save order; tick photos, then Remove with a typed REMOVE
    var pl = document.getElementById('pgphotos');
    if (pl) {
      var orderIn = document.getElementById('pgorderv'), saveBtn = document.getElementById('pgordbtn'), first = orderIn.value, said = document.getElementById('pgmoved');
      var sync = function () {
        orderIn.value = [].map.call(pl.children, function (li) { return li.getAttribute('data-id'); }).join(',');
        saveBtn.disabled = orderIn.value === first;
      };
      pl.addEventListener('click', function (e) {
        var b = e.target.closest('[data-move]'); if (!b) return;
        var li = b.closest('li'), dir = +b.getAttribute('data-move'), to = dir < 0 ? li.previousElementSibling : li.nextElementSibling;
        if (!to) return;
        if (dir < 0) pl.insertBefore(li, to); else pl.insertBefore(to, li);
        b.focus(); sync();
        said.textContent = 'Photo ' + li.getAttribute('data-id') + ' is now number ' + ([].indexOf.call(pl.children, li) + 1) + ' of ' + pl.children.length + '.';
      });
      var dragged = null;
      pl.addEventListener('dragstart', function (e) { dragged = e.target.closest('li'); if (!dragged) return; dragged.classList.add('drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', dragged.getAttribute('data-id')); } catch (x) {} });
      pl.addEventListener('dragover', function (e) { var li = e.target.closest('li'); if (!dragged || !li) return; e.preventDefault(); [].forEach.call(pl.children, function (x) { x.classList.toggle('over', x === li && x !== dragged); }); });
      pl.addEventListener('drop', function (e) {
        var li = e.target.closest('li'); if (!dragged || !li || li === dragged) return; e.preventDefault();
        var r = li.getBoundingClientRect(), after = (e.clientX - r.left) > r.width / 2;
        pl.insertBefore(dragged, after ? li.nextElementSibling : li); sync();
      });
      pl.addEventListener('dragend', function () { if (dragged) dragged.classList.remove('drag'); [].forEach.call(pl.children, function (x) { x.classList.remove('over'); }); dragged = null; });
      var rmOpen = document.getElementById('pgrmopen'), rm = document.getElementById('pgrm'), rmN = document.getElementById('pgrmn');
      var ticked = function () { return document.querySelectorAll('#pgphotos input[type=checkbox]:checked').length; };
      pl.addEventListener('change', function () { var n = ticked(); rmOpen.disabled = n === 0; rmOpen.textContent = n ? 'Remove ' + n + ' selected photo' + (n === 1 ? '' : 's') : 'Remove selected photos'; if (!n) rm.hidden = true; rmN.textContent = n; });
      rmOpen.addEventListener('click', function () { rmN.textContent = ticked(); rm.hidden = false; rm.querySelector('input[name=confirm]').focus(); });
      document.getElementById('pgrmcancel').addEventListener('click', function () { rm.hidden = true; rmOpen.focus(); });
    }

    // Event series: Move up / Move down reorder the rows (the form sends them in page order); the slug preview follows the name
    var sl = document.getElementById('serlist');
    if (sl) sl.addEventListener('click', function (e) {
      var b = e.target.closest('[data-sermove]'); if (!b) return;
      var li = b.closest('li'), up = b.getAttribute('data-sermove') === '-1', to = up ? li.previousElementSibling : li.nextElementSibling;
      if (!to) return;
      if (up) sl.insertBefore(li, to); else sl.insertBefore(to, li);
      b.focus();
      document.getElementById('sermoved').textContent = li.querySelector('legend').firstChild.textContent.trim() + ' is now number ' + ([].indexOf.call(sl.children, li) + 1) + ' of ' + sl.children.length + '.';
    });
    document.querySelectorAll('input[data-slugto]').forEach(function (n) {
      var out = document.querySelector(n.getAttribute('data-slugto'));
      var f = function () { out.textContent = n.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, ''); };
      n.addEventListener('input', f); if (n.value) f();
    });

    // Site settings, featured portraits: Up / Down / Remove, and Add from the portrait list
    var fl = document.getElementById('fplist');
    if (fl) {
      var said2 = document.getElementById('fpmoved');
      fl.addEventListener('click', function (e) {
        var b = e.target.closest('[data-fp]'); if (!b) return;
        var li = b.closest('li'), d = +b.getAttribute('data-fp'), name = li.querySelector('span').textContent;
        if (d === 0) { var nx = li.nextElementSibling || li.previousElementSibling; li.remove(); said2.textContent = name + ' removed.'; if (nx) nx.querySelector('button').focus(); return; }
        var to = d < 0 ? li.previousElementSibling : li.nextElementSibling; if (!to) return;
        if (d < 0) fl.insertBefore(li, to); else fl.insertBefore(to, li);
        b.focus(); said2.textContent = name + ' is now number ' + ([].indexOf.call(fl.children, li) + 1) + ' of ' + fl.children.length + '.';
      });
      document.getElementById('fpaddbtn').addEventListener('click', function () {
        var sel = document.getElementById('fpadd'), v = sel.value; if (!v) return;
        if (fl.querySelector('input[value="' + v.replace(/"/g, '') + '"]')) { said2.textContent = 'That gallery is already in the list.'; return; }
        var li = document.createElement('li'), inp = document.createElement('input'), sp = document.createElement('span'), acts = document.createElement('span');
        inp.type = 'hidden'; inp.name = 'featured[]'; inp.value = v; sp.textContent = sel.options[sel.selectedIndex].text; acts.className = 'acts';
        [['-1', 'Up'], ['1', 'Down'], ['0', 'Remove']].forEach(function (x) { var bb = document.createElement('button'); bb.type = 'button'; bb.className = 'btn sm alt'; bb.setAttribute('data-fp', x[0]); bb.textContent = x[1]; acts.appendChild(bb); });
        li.appendChild(inp); li.appendChild(sp); li.appendChild(acts); fl.appendChild(li); sel.value = '';
        said2.textContent = sp.textContent + ' added at the end.';
      });
    }

    // Gallery editor: Venue is for events only, so it shows only while Section is Events
    var vb = document.getElementById('venuebox'), cs = vb && vb.closest('form').querySelector('select[name=category]');
    if (cs) cs.addEventListener('change', function () { vb.hidden = cs.value !== 'events'; });

    // Gallery editor: "Suggest with Claude" fills Tags and Description with a draft (nothing is saved until Save gallery)
    var aib = document.getElementById('aisuggest');
    if (aib) aib.addEventListener('click', function () {
      var f = aib.closest('form'), st = document.getElementById('aistat'), tg = f.querySelector('textarea[name=tags]'), ds = f.querySelector('textarea[name=description]');
      if ((tg.value.trim() || ds.value.trim()) && !window.confirm('Replace what is in Tags and Description with Claude\'s draft? You can still edit it before saving.')) return;
      var fd = new FormData(); fd.append('a', 'pgsuggest'); fd.append('slug', aib.getAttribute('data-slug')); fd.append('csrf', f.querySelector('input[name=csrf]').value);
      ['title', 'date', 'category', 'series', 'venue', 'description'].forEach(function (k) { var el = f.querySelector('[name=' + k + ']'); if (el) fd.append(k, el.value); });
      var nt = document.getElementById('ainotes'); if (nt) fd.append('notes', nt.value);
      aib.disabled = true; st.textContent = 'Asking Claude. This usually takes 15 to 60 seconds...';
      fetch('/client/admin/', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json().catch(function () { return { error: 'The server returned an unexpected answer.' }; }); })
        .catch(function () { return { error: 'Network problem. Try again.' }; })
        .then(function (j) {
          aib.disabled = false;
          if (!j || !j.ok) { st.textContent = (j && j.error) || 'Something went wrong. Try again.'; return; }
          tg.value = j.tags.join('\n'); ds.value = j.description;
          [tg, ds].forEach(function (el) { el.classList.add('ai-new'); });
          st.textContent = 'Draft filled in from ' + j.photos + ' photos. Check it, fix anything that is not right, then click Save gallery.';
        });
    });

    // Services: cards move, get removed or added; new cards get the next free index for their field names
    var cl = document.getElementById('cardlist');
    if (cl) {
      var said3 = document.getElementById('svcmoved'), next = cl.children.length;
      cl.addEventListener('click', function (e) {
        var b = e.target.closest('[data-card]'); if (!b) return;
        var li = b.closest('li'), d = +b.getAttribute('data-card'), name = li.querySelector('legend').textContent;
        if (d === 0) { if (cl.children.length < 2) { said3.textContent = 'Keep at least one card.'; return; } if (!window.confirm('Remove the card "' + name + '"? It is gone once you save.')) return; li.remove(); said3.textContent = name + ' removed. Save to keep the change.'; return; }
        var to = d < 0 ? li.previousElementSibling : li.nextElementSibling; if (!to) return;
        if (d < 0) cl.insertBefore(li, to); else cl.insertBefore(to, li);
        b.focus(); said3.textContent = name + ' is now card ' + ([].indexOf.call(cl.children, li) + 1) + ' of ' + cl.children.length + '.';
      });
      document.getElementById('cardadd').addEventListener('click', function () {
        var li = document.getElementById('cardtpl').content.firstElementChild.cloneNode(true), n = next++;
        li.querySelectorAll('[data-n]').forEach(function (el) { el.name = 'cards[' + n + '][' + el.getAttribute('data-n') + ']'; });
        cl.appendChild(li); li.querySelector('input[data-n=title]').focus(); said3.textContent = 'New card added at the end.';
      });
    }

    // Session page: the Share button opens and closes the share panel
    document.querySelectorAll('[data-share]').forEach(function (b) {
      var box = document.querySelector(b.getAttribute('data-share')); if (!box) return;
      b.addEventListener('click', function () { var open = box.hidden; box.hidden = !open; b.setAttribute('aria-expanded', open ? 'true' : 'false'); if (open) box.querySelector('button, a').focus(); });
    });

    // Session Photos tab: Select mode for several photos at once (bulkphotos)
    var sm = document.getElementById('selmode'), grid = document.getElementById('photogrid'), bar = document.getElementById('selbar');
    if (sm && grid && bar) {
      var boxes = function () { return [].slice.call(grid.querySelectorAll('.selpick')); };
      var refresh = function () {
        var on = boxes().filter(function (b) { return b.checked; });
        boxes().forEach(function (b) { b.closest('.cd').classList.toggle('picked', b.checked); });
        document.getElementById('selcount').textContent = on.length + ' selected';
        document.getElementById('selids').value = on.map(function (b) { return b.value; }).join(',');
      };
      sm.addEventListener('click', function () {
        var on = !grid.classList.contains('selecting');
        grid.classList.toggle('selecting', on); bar.hidden = !on; sm.setAttribute('aria-pressed', on ? 'true' : 'false');
        sm.textContent = on ? 'Done selecting' : 'Select photos';
        if (!on) { boxes().forEach(function (b) { b.checked = false; }); refresh(); }
      });
      grid.addEventListener('change', function (e) { if (e.target.classList.contains('selpick')) refresh(); });
      grid.addEventListener('click', function (e) {   // in Select mode, tapping a photo toggles it
        if (!grid.classList.contains('selecting') || e.target.closest('input, button, label, form')) return;
        var cd = e.target.closest('.cd'); if (!cd) return;
        var b = cd.querySelector('.selpick'); b.checked = !b.checked; refresh();
      });
      bar.addEventListener('click', function (e) {
        var b = e.target.closest('[data-sel]'); if (!b) return;
        var k = b.getAttribute('data-sel');
        boxes().forEach(function (x) { x.checked = k === 'all' || (k === 'fav' && x.closest('.cd').getAttribute('data-fav') === '1'); });
        refresh();
      });
      var lastDo = '';
      bar.addEventListener('click', function (e) { var b = e.target.closest('button[name=do]'); if (b) lastDo = b.value; }, true);
      bar.addEventListener('submit', function (e) {
        refresh(); var n = boxes().filter(function (b) { return b.checked; }).length;
        var d = (e.submitter && e.submitter.value) || lastDo;
        if (!n) { e.preventDefault(); window.alert('Select at least one photo first.'); return; }
        if (d === 'delete' && !window.confirm('Delete ' + n + ' photo' + (n > 1 ? 's' : '') + ' and ' + (n > 1 ? 'their originals' : 'its original') + '? This cannot be undone.')) e.preventDefault();
      });
    }

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
