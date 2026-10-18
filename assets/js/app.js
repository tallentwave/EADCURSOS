(function () {
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';

  // confirmação em ações destrutivas
  document.addEventListener('submit', function (e) {
    var f = e.target, m = f.getAttribute('data-confirm');
    if (m && !window.confirm(m)) e.preventDefault();
  });

  // marcar aula como concluída (AJAX)
  var btn = document.getElementById('btn-complete');
  if (btn) {
    btn.addEventListener('click', function () {
      var done = btn.getAttribute('data-done') === '1' ? 0 : 1;
      var body = new URLSearchParams({ lesson_id: btn.getAttribute('data-lesson'), completed: String(done), _csrf: csrf });
      btn.disabled = true;
      fetch(btn.getAttribute('data-url'), { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error();
          btn.setAttribute('data-done', done ? '1' : '0');
          btn.textContent = done ? '✓ Aula concluída' : 'Marcar como concluída';
          btn.classList.toggle('btn-ghost', !!done);
          var cur = document.querySelector('.lesson-item.current');
          if (cur) {
            cur.classList.toggle('done', !!done);
            var dot = cur.querySelector('.dot'); if (dot) dot.textContent = done ? '✓' : '';
          }
          var bar = document.querySelector('.side .bar i'); if (bar) bar.style.width = j.pct + '%';
          var lab = document.getElementById('pct-label'); if (lab) lab.textContent = j.pct + '% concluído';
          var next = btn.getAttribute('data-next');
          if (done && next) window.location.href = next;
        })
        .catch(function () { alert('Não foi possível salvar o progresso.'); })
        .finally(function () { btn.disabled = false; });
    });
  }

  // pré-visualização do cupom
  var bc = document.getElementById('btn-coupon');
  if (bc) {
    bc.addEventListener('click', function () {
      var msg = document.getElementById('coupon-msg'), box = document.getElementById('price-box');
      var body = new URLSearchParams({ course_id: bc.getAttribute('data-course'), coupon: document.getElementById('coupon').value, _csrf: csrf });
      fetch(bc.getAttribute('data-url'), { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          msg.style.color = j.ok ? 'var(--ok)' : 'var(--danger)';
          msg.textContent = j.msg || '';
          box.textContent = j.ok && j.final ? j.final : box.getAttribute('data-price');
        }).catch(function () { msg.textContent = 'Erro ao validar o cupom.'; });
    });
  }

  // responder comentário
  document.querySelectorAll('[data-reply]').forEach(function (a) {
    a.addEventListener('click', function (ev) {
      ev.preventDefault();
      var f = document.getElementById('comment-form');
      f.querySelector('[name=parent_id]').value = a.getAttribute('data-reply');
      f.querySelector('textarea').focus();
      document.getElementById('reply-to').textContent = 'Respondendo a ' + a.getAttribute('data-name') + ' (clique em Enviar)';
    });
  });
})();
