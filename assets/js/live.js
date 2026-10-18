(function () {
  var p = document.getElementById('live-panel');
  if (!p) return;
  var off = parseInt(p.getAttribute('data-now'), 10) - Math.floor(Date.now() / 1000);   // corrige o relógio do aluno pelo do servidor
  var T = { open: +p.dataset.open, start: +p.dataset.start, end: +p.dataset.end, close: +p.dataset.close };
  var out = document.getElementById('live-count');
  function stateAt(n) { return n < T.open ? 'upcoming' : n < T.start ? 'soon' : n < T.end ? 'live' : n < T.close ? 'late' : 'ended'; }
  var first = stateAt(Math.floor(Date.now() / 1000) + off);
  function fmt(s) {
    s = Math.max(0, s);
    var d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60), x = s % 60;
    if (d > 0) return d + (d === 1 ? ' dia ' : ' dias ') + h + 'h';
    if (h > 0) return h + 'h ' + (m < 10 ? '0' : '') + m + 'min';
    return m + 'min ' + (x < 10 ? '0' : '') + x + 's';
  }
  function tick() {
    var n = Math.floor(Date.now() / 1000) + off, st = stateAt(n);
    if (st !== first) { window.location.reload(); return; }
    if (!out) return;
    if (st === 'upcoming') out.textContent = 'Começa em ' + fmt(T.start - n);
    else if (st === 'soon') out.textContent = 'Começa em ' + fmt(T.start - n) + ' — a sala já está aberta';
    else if (st === 'live') out.textContent = 'Acontecendo agora — termina em cerca de ' + fmt(T.end - n);
    else if (st === 'late') out.textContent = 'A transmissão está terminando';
    else out.textContent = '';
  }
  tick(); setInterval(tick, 1000);
})();
