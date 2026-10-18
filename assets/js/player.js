/* Player protegido: sem controles nativos, sem aceleração, sem avanço e com contagem de tempo validada no servidor. */
(function () {
  var stage = document.getElementById('player');
  if (!stage) return;
  var wrap = stage.parentNode;
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var lessonId = stage.getAttribute('data-lesson');
  var fillEl = document.getElementById('watch-fill');
  var statusEl = document.getElementById('watch-status');
  var nextBtn = document.getElementById('btn-next');
  var info = null, ad = null, maxLocal = 0, lastCur = 0, doneFlag = false, lastPing = 0;

  function el(tag, cls) { var e = document.createElement(tag); if (cls) e.className = cls; return e; }
  function fmt(t) { t = Math.max(0, Math.floor(t || 0)); var m = Math.floor(t / 60), s = t % 60; return m + ':' + (s < 10 ? '0' : '') + s; }
  function fail(msg) { stage.innerHTML = ''; var d = el('div', 'msg'); d.textContent = msg || 'Não foi possível carregar o vídeo.'; stage.appendChild(d); }
  function loadScript(src, cb) { var s = document.createElement('script'); s.src = src; s.onload = cb; s.onerror = function () { fail(); }; document.head.appendChild(s); }

  wrap.addEventListener('contextmenu', function (e) { e.preventDefault(); });
  wrap.addEventListener('dragstart', function (e) { e.preventDefault(); });

  /* ---------- adaptadores ---------- */
  function ytAdapter(src, ready) {
    function build() {
      var host = el('div'); stage.appendChild(host);
      var p = null, st = -1;
      var api = {
        play: function () { p.playVideo(); }, pause: function () { p.pauseVideo(); },
        time: function () { return p.getCurrentTime() || 0; }, seek: function (t) { p.seekTo(t, true); },
        playing: function () { return st === 1; }, duration: function () { return p.getDuration() || 0; },
        fixRate: function () { if (p.getPlaybackRate() !== 1) p.setPlaybackRate(1); }, onstate: null
      };
      p = new YT.Player(host, {
        host: 'https://www.youtube-nocookie.com', videoId: src.id,
        playerVars: { controls: 0, disablekb: 1, fs: 0, rel: 0, modestbranding: 1, iv_load_policy: 3, playsinline: 1, cc_load_policy: 0, origin: location.origin, start: Math.floor(info.start || 0) },
        events: {
          onReady: function () { ready(api); },
          onStateChange: function (e) { st = e.data; if (api.onstate) api.onstate(st === 1, st === 0); }
        }
      });
    }
    if (window.YT && YT.Player) build(); else { window.onYouTubeIframeAPIReady = build; loadScript('https://www.youtube.com/iframe_api', function () {}); }
  }

  function vimeoAdapter(src, ready) {
    loadScript('https://player.vimeo.com/api/player.js', function () {
      var host = el('div'); host.style.cssText = 'position:absolute;inset:0'; stage.appendChild(host);
      var cur = 0, pl = false, d = 0;
      var p = new Vimeo.Player(host, { url: 'https://vimeo.com/' + src.id + (src.h ? '/' + src.h : ''), controls: false, keyboard: false, pip: false, title: false, byline: false, portrait: false, dnt: true, playsinline: true });
      var api = {
        play: function () { p.play().catch(function () {}); }, pause: function () { p.pause().catch(function () {}); },
        time: function () { return cur; }, seek: function (t) { p.setCurrentTime(t).catch(function () {}); },
        playing: function () { return pl; }, duration: function () { return d; },
        fixRate: function () {}, onstate: null
      };
      p.on('timeupdate', function (e) { cur = e.seconds; d = e.duration; });
      p.on('play', function () { pl = true; if (api.onstate) api.onstate(true, false); });
      p.on('pause', function () { pl = false; if (api.onstate) api.onstate(false, false); });
      p.on('ended', function () { pl = false; if (api.onstate) api.onstate(false, true); });
      p.on('playbackratechange', function () { p.setPlaybackRate(1).catch(function () {}); });
      p.ready().then(function () {
        p.getDuration().then(function (x) { d = x; });
        if (info.start) p.setCurrentTime(info.start).catch(function () {});
        ready(api);
      }).catch(function () { fail(); });
    });
  }

  /* ---------- interface ---------- */
  var shield, btn, fill, time;
  function buildUI() {
    shield = el('div', 'shield paused');
    var bar = el('div', 'pbar');
    btn = el('button'); btn.type = 'button'; btn.innerHTML = '&#9654;'; btn.setAttribute('aria-label', 'Play/Pause');
    var track = el('div', 'ptrack'); fill = el('i'); track.appendChild(fill);
    time = el('span', 'ptime'); time.textContent = '0:00';
    var fs = el('button'); fs.type = 'button'; fs.innerHTML = '&#x26F6;'; fs.setAttribute('aria-label', 'Tela cheia');
    bar.appendChild(btn); bar.appendChild(track); bar.appendChild(time); bar.appendChild(fs);
    stage.appendChild(shield); stage.appendChild(bar);
    function toggle() { if (ad.playing()) ad.pause(); else ad.play(); }
    shield.addEventListener('click', toggle); btn.addEventListener('click', toggle);
    fs.addEventListener('click', function () {
      var f = wrap.requestFullscreen || wrap.webkitRequestFullscreen;
      if (document.fullscreenElement) document.exitFullscreen(); else if (f) f.call(wrap);
    });
  }

  function paintState(playing) { shield.classList.toggle('paused', !playing); btn.innerHTML = playing ? '&#10074;&#10074;' : '&#9654;'; }

  function applyStatus(j) {
    if (!j || !j.ok) return;
    if (fillEl) fillEl.style.width = j.pct + '%';
    if (j.done) {
      if (statusEl) statusEl.innerHTML = '&#10003; Aula concluída — a próxima aula está liberada';
      if (!doneFlag) {
        doneFlag = true;
        if (nextBtn) { nextBtn.classList.remove('disabled'); nextBtn.removeAttribute('aria-disabled'); nextBtn.setAttribute('href', nextBtn.getAttribute('data-href')); nextBtn.classList.remove('btn-ghost'); }
        var cur = document.querySelector('.lesson-item.current');
        if (cur) { cur.classList.add('done'); var dot = cur.querySelector('.dot'); if (dot) dot.innerHTML = '&#10003;'; }
        var nx = cur && cur.nextElementSibling;
        if (nx && nx.classList.contains('lesson-item') && nextBtn) { nx.setAttribute('href', nextBtn.getAttribute('data-href')); var nd = nx.querySelector('.dot'); if (nd) nd.innerHTML = ''; }
        if (typeof j.course_pct === 'number') {
          var bar = document.querySelector('.side .bar i'); if (bar) bar.style.width = j.course_pct + '%';
          var lab = document.getElementById('pct-label'); if (lab) lab.textContent = j.course_pct + '% concluído';
        }
      }
    } else if (statusEl) {
      statusEl.textContent = 'Assistido ' + j.pct + '% — assista até o final para liberar a próxima aula';
    }
    // barrinha e % da aula atual na lista lateral
    var curItem = document.querySelector('.lesson-item.current');
    if (curItem) {
      var lb = curItem.querySelector('.lbar i'); if (lb) lb.style.width = j.pct + '%';
      var lp = curItem.querySelector('.lpct'); if (lp) lp.textContent = j.pct + '%';
    }
  }

  /* mode: undefined = batimento; 'resume' = marca o início de um trecho (sem crédito);
     'pause'/'end' = fecha o trecho que estava tocando (credita o tempo real até agora). */
  function ping(mode) {
    if (!ad || info.bypass) return;
    var now = Date.now(); if (!mode && now - lastPing < 1500) return; lastPing = now;
    var playing = mode === 'resume' ? '0' : (mode ? '1' : (ad.playing() ? '1' : '0'));
    var pos = ad.time(), d = ad.duration();
    if (mode === 'end' && d) pos = Math.max(pos, d);
    var b = new URLSearchParams({ lesson_id: lessonId, pos: String(pos), playing: playing, dur: String(d), _csrf: csrf });
    fetch(stage.getAttribute('data-ping'), { method: 'POST', body: b, credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).then(applyStatus).catch(function () {});
  }

  function ready(a) {
    ad = a;
    stage.querySelectorAll('.msg').forEach(function (m) { m.remove(); });
    buildUI();
    maxLocal = info.start || 0; lastCur = maxLocal;
    ad.onstate = function (playing, ended) {
      paintState(playing);
      ping(playing ? 'resume' : (ended ? 'end' : 'pause'));
      if (ended && info.bypass && statusEl) statusEl.innerHTML = '&#10003; Fim da aula';
    };
    if (info.done) applyStatus({ ok: true, done: true, pct: 100 });
    if (info.bypass) {
      var note = document.getElementById('watch-status');
      if (note && !info.done) note.textContent = 'Modo livre (administrador ou aula de degustação): sem travas.';
    }

    setInterval(function () {                        // fiscal: velocidade e avanço
      var c = ad.time();
      if (!info.bypass) {
        ad.fixRate();
        if (c - lastCur >= -0.3 && c - lastCur <= 1.4) { if (c > maxLocal) maxLocal = c; }
        else if (c > maxLocal + 1.5) { ad.seek(maxLocal); c = maxLocal; }
        lastCur = c;
      }
      var d = ad.duration();
      time.textContent = fmt(c) + ' / ' + fmt(d);
      fill.style.width = d ? Math.min(100, c * 100 / d) + '%' : '0';
    }, 500);
    setInterval(function () { if (ad.playing()) ping(); }, 5000);
    document.addEventListener('visibilitychange', function () { if (document.hidden) ad.pause(); }); // aba em segundo plano: pausa
  }

  fetch(stage.getAttribute('data-src'), { credentials: 'same-origin', cache: 'no-store' })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (!j.ok) return fail(j.msg);
      info = j;
      if (j.provider === 'youtube') ytAdapter(j, ready); else vimeoAdapter(j, ready);
    }).catch(function () { fail(); });
})();
