(function () {
  var f = document.getElementById('exam-form');
  if (!f) return;
  var sending = false;
  f.addEventListener('submit', function (e) {
    if (sending) { e.preventDefault(); return; }
    if (!f.getAttribute('data-auto') && !window.confirm(f.getAttribute('data-msg') || 'Enviar?')) { e.preventDefault(); return; }
    sending = true; var b = document.getElementById('exam-submit'); if (b) b.disabled = true;
  });
  var left = parseInt(f.getAttribute('data-remaining'), 10) || 0, out = document.getElementById('exam-left');
  if (!out) return;
  function paint() { var m = Math.floor(left / 60), s = left % 60; out.textContent = m + ':' + (s < 10 ? '0' : '') + s; if (left <= 60) out.parentNode.classList.add('low'); }
  paint();
  var iv = setInterval(function () {
    left--; if (left <= 0) { clearInterval(iv); left = 0; paint(); f.setAttribute('data-auto', '1'); f.submit(); return; } paint();
  }, 1000);
})();
