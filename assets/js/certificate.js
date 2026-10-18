(function () {
  var vp = document.getElementById('cert-viewport'), c = document.getElementById('cert');
  if (!vp || !c) return;
  function fit() {
    var w = c.offsetWidth, h = c.offsetHeight, s = Math.min(1, (document.body.clientWidth - 24) / w);
    c.style.transform = 'scale(' + s + ')'; vp.style.width = (w * s) + 'px'; vp.style.height = (h * s) + 'px';
  }
  function reset() { c.style.transform = 'none'; vp.style.width = ''; vp.style.height = ''; }
  fit();
  window.addEventListener('resize', fit);
  window.addEventListener('beforeprint', reset);
  window.addEventListener('afterprint', fit);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-print]') : null;
    if (!b) return;
    e.preventDefault();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { window.print(); }); else window.print();
  });
})();
