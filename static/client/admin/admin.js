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
    var sel = document.getElementById('eaTheme');
    if (sel) {
      sel.value = getMode();
      sel.addEventListener('change', function () { try { localStorage.setItem('eaTheme', sel.value); } catch (e) {} applyMode(sel.value); });
    }
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
})();
