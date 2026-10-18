<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$lesson = q_one('SELECT l.*, m.course_id, m.title AS module_title FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$lesson) { http_response_code(404); exit('Aula não encontrada.'); }
$cid = (int)$lesson['course_id'];
$course = course_by_id($cid);
if ($course['status'] !== 'published' && $u['role'] !== 'admin') { http_response_code(404); exit('Curso indisponível.'); }
if (!has_access($uid, $cid) && !$lesson['is_free']) {
    flash('err', 'Você ainda não tem acesso a este curso.');
    redirect('curso.php?id=' . $cid);
}

$isOwner = has_access($uid, $cid);
$openMap = $isOwner ? open_map($uid, $cid) : [];
$pcts = $isOwner ? lesson_pcts($uid, $cid) : [];
if ($isOwner && !($openMap[(int)$lesson['id']] ?? false)) {
    if ($blk = first_blocking_exam($uid, $cid)) { flash('err', 'Faça a prova do módulo anterior para liberar esta aula.'); redirect('prova.php?id=' . $blk['id']); }
    if (!empty($lesson['release_at']) && strtotime((string)$lesson['release_at']) > time()) { flash('info', 'Esta aula será liberada em ' . live_when($lesson['release_at']) . '.'); redirect('curso.php?id=' . $cid); }
    flash('err', 'Conclua a aula anterior para liberar esta.');
    $nl = next_lesson($uid, $cid);
    redirect($nl && $nl['id'] != $lesson['id'] ? 'aula.php?id=' . $nl['id'] : 'curso.php?id=' . $cid);
}

// registra "visto por último" sem marcar como concluída (para "continuar assistindo")
q('INSERT INTO progress (user_id, lesson_id, completed, updated_at) VALUES (?, ?, 0, ?)
   ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)', [$uid, $lesson['id'], now()]);

$all = course_lessons($cid);
$ids = array_column($all, 'id');
$pos = array_search($lesson['id'], $ids);
$prev = $pos > 0 ? $all[$pos - 1] : null;
$next = $pos < count($all) - 1 ? $all[$pos + 1] : null;
$done = array_flip(array_column(q_all('SELECT lesson_id FROM progress WHERE user_id = ? AND completed = 1', [$uid]), 'lesson_id'));
$isDone = isset($done[$lesson['id']]);
$prog = course_progress($uid, $cid);
$bySec = [];
foreach ($all as $l) $bySec[$l['module_id']][] = $l;
$mods = q_all('SELECT * FROM modules WHERE course_id = ? ORDER BY position, id', [$cid]);
$isLive = lesson_is_live($lesson);
$liveState = $isLive ? live_state($lesson) : '';
$liveTimes = $isLive ? live_times($lesson) : null;
$isAdminUser = $u['role'] === 'admin';
// aula ao vivo: a gravação só aparece depois do fim da transmissão (admin vê sempre)
$embed = video_embed($lesson['video_url']);
if ($isLive && $embed && !$isAdminUser && !live_replay_open($lesson)) $embed = null;
$attended = $isLive ? q_val('SELECT joined_at FROM live_attendance WHERE user_id = ? AND lesson_id = ?', [$uid, $lesson['id']]) : null;
$canEnter = $isLive && ($isOwner || $lesson['is_free']) && ($isAdminUser ? !empty($lesson['live_url']) : live_can_join($lesson));
$materials = q_all('SELECT * FROM materials WHERE lesson_id = ? ORDER BY id', [$lesson['id']]);
$comments = q_all('SELECT c.*, u.name, u.role FROM comments c JOIN users u ON u.id = c.user_id WHERE c.lesson_id = ? ORDER BY c.created_at', [$lesson['id']]);
$top = []; $replies = [];
foreach ($comments as $cm) { if ($cm['parent_id']) $replies[$cm['parent_id']][] = $cm; else $top[] = $cm; }

function render_comment(array $cm, array $replies, bool $isReply = false): void {
    echo '<div class="comment' . ($isReply ? ' reply' : '') . '"><div class="who">' . e($cm['name']);
    if ($cm['role'] === 'admin') echo '<span class="badge">PROFESSOR</span>';
    echo '<small>' . e(date('d/m/Y H:i', strtotime($cm['created_at']))) . '</small></div><div class="txt">' . e($cm['body']) . '</div>';
    if (!$isReply) echo '<a href="#comment-form" data-reply="' . (int)$cm['id'] . '" data-name="' . e($cm['name']) . '"><small>Responder</small></a>';
    echo '</div>';
    foreach ($replies[$cm['id']] ?? [] as $r) render_comment($r, $replies, true);
}

$title = $lesson['title'];
$withPlayer = (bool)$embed;
$withLive = $isLive && $liveTimes;
require ROOT . '/app/views/header.php';
?>
<div class="player-layout">
  <div>
    <?php if ($isLive): ?>
    <div class="live-panel state-<?= e($liveState) ?>" id="live-panel"<?php if ($liveTimes): ?> data-now="<?= time() ?>" data-open="<?= $liveTimes['open'] ?>" data-start="<?= $liveTimes['start'] ?>" data-end="<?= $liveTimes['end'] ?>" data-close="<?= $liveTimes['close'] ?>"<?php endif; ?>>
      <div class="live-kicker"><?php
        echo ['unscheduled' => 'Aula ao vivo', 'upcoming' => '&#128197; Aula ao vivo agendada', 'soon' => '&#128308; Sala aberta', 'live' => '&#128308; AO VIVO AGORA', 'late' => '&#128308; Transmissão terminando', 'ended' => 'Aula ao vivo encerrada'][$liveState]; ?></div>
      <h2><?= $lesson['starts_at'] ? e(live_when($lesson['starts_at'])) : 'Data a definir' ?></h2>
      <?php if ($liveTimes): ?><p class="live-meta">Duração prevista: <?= e(fmt_workload((int)round(($liveTimes['end'] - $liveTimes['start']) / 60))) ?></p><p class="live-count" id="live-count"></p><?php endif; ?>
      <div class="live-actions">
        <?php if ($canEnter): ?>
          <a class="btn btn-live" target="_blank" rel="noopener noreferrer" href="<?= e(url('ao-vivo.php?id=' . $lesson['id'])) ?>">&#127909; Entrar na aula ao vivo</a>
        <?php elseif ($liveState === 'ended'): ?>
          <span class="live-note"><?= $embed ? 'A gravação desta aula está logo abaixo.' : 'Esta aula já aconteceu. A gravação será publicada aqui, se o professor disponibilizar.' ?></span>
        <?php elseif (empty($lesson['live_url'])): ?>
          <span class="live-note">O professor ainda vai divulgar o link da sala. Ele aparecerá aqui 15 minutos antes do início.</span>
        <?php elseif ($liveState === 'upcoming'): ?>
          <button class="btn" disabled>O botão de entrada libera 15 min antes</button>
        <?php else: ?><span class="live-note">Faça login com a conta matriculada para entrar.</span><?php endif; ?>
        <?php if ($liveTimes && $liveState !== 'ended' && ($isOwner || $lesson['is_free'])): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('aula_ics.php?id=' . $lesson['id'])) ?>">&#128197; Adicionar à minha agenda</a><?php endif; ?>
      </div>
      <?php if ($attended): ?><p class="live-presence">&#10003; Presença registrada em <?= e(date('d/m/Y \à\s H:i', strtotime((string)$attended))) ?></p><?php endif; ?>
      <?php if ($isLive && $isAdminUser): ?><p class="hint" style="margin-top:8px">Modo administrador: você pode entrar a qualquer momento para testar o link.</p><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($embed): ?>
      <div class="video" id="player-wrap"><div class="stage" id="player" data-lesson="<?= (int)$lesson['id'] ?>" data-src="<?= e(url('video.php?id=' . $lesson['id'])) ?>" data-ping="<?= e(url('watch.php')) ?>"><div class="msg">Carregando vídeo...</div></div></div>
      <div class="watchbar" id="watchbar"><div class="track"><i id="watch-fill" style="width:<?= $isDone ? 100 : (int)($pcts[$lesson['id']] ?? 0) ?>%"></i></div><span id="watch-status"><?= $isDone ? '&#10003; Aula concluída' : ($isOwner ? 'Assista a aula inteira para liberar a próxima' : 'Aula de degustação') ?></span></div>
    <?php elseif (!$isLive): ?>
      <div class="video empty">Vídeo não disponível para esta aula.</div>
    <?php endif; ?>
    <div class="lesson-head">
      <div><small style="color:var(--muted)"><?= e($lesson['module_title']) ?></small><h1><?= e($lesson['title']) ?></h1></div>
      <?php if (!$embed && !$isLive): ?>
      <button id="btn-complete" class="btn <?= $isDone ? 'btn-ghost' : '' ?>" data-lesson="<?= (int)$lesson['id'] ?>" data-done="<?= $isDone ? 1 : 0 ?>"
        data-url="<?= e(url('progresso.php')) ?>" data-next="<?= $next && (!$isOwner || ($openMap[$next['id']] ?? true)) && ($isOwner || $next['is_free']) ? e(url('aula.php?id=' . $next['id'])) : '' ?>">
        <?= $isDone ? '&#10003; Aula concluída' : 'Marcar como concluída' ?></button>
      <?php endif; ?>
    </div>
    <div class="toolbar">
      <?php if ($prev): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('aula.php?id=' . $prev['id'])) ?>">&laquo; Anterior</a><?php endif; ?>
      <?php if ($next):
          $nextOk = !$isOwner ? (bool)$next['is_free'] : (($openMap[$next['id']] ?? false) || $isDone); ?>
      <a id="btn-next" class="btn btn-ghost btn-sm <?= $nextOk ? '' : 'disabled' ?>" data-href="<?= e(url('aula.php?id=' . $next['id'])) ?>" <?= $nextOk ? 'href="' . e(url('aula.php?id=' . $next['id'])) . '"' : 'aria-disabled="true"' ?>>Próxima &raquo;</a><?php endif; ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('curso.php?id=' . $cid)) ?>">Página do curso</a>
      <?php if ($isOwner && certificate_eligible($uid, $cid)[0]): ?><a class="btn btn-blue btn-sm" href="<?= e(url('certificado.php?curso=' . $cid)) ?>">&#127942; Certificado</a><?php endif; ?>
      <?php $lastInModule = !$next || (int)$next['module_id'] !== (int)$lesson['module_id'];
            $me = $lastInModule ? module_exam((int)$lesson['module_id']) : null;
            $fe = !$next ? final_exam($cid) : null;
            if (($isOwner || $u['role'] === 'admin') && exam_ready($me)): ?><a class="btn btn-blue btn-sm" href="<?= e(url('prova.php?id=' . $me['id'])) ?>">&#128221; Prova do módulo</a><?php endif; ?>
      <?php if (($isOwner || $u['role'] === 'admin') && exam_ready($fe)): ?><a class="btn btn-blue btn-sm" href="<?= e(url('prova.php?id=' . $fe['id'])) ?>">&#128221; Avaliação final</a><?php endif; ?>
    </div>
    <?php if (trim((string)$lesson['description']) !== ''): ?>
      <div class="panel"><h3>Sobre esta aula</h3><p style="white-space:pre-wrap"><?= e($lesson['description']) ?></p></div>
    <?php endif; ?>
    <?php if ($materials): ?>
      <div class="panel"><h3>Materiais para download</h3>
      <?php foreach ($materials as $m): ?>
        <div class="mat">&#128196; <a href="<?= e(url('material.php?id=' . $m['id'])) ?>"><?= e($m['title']) ?></a><small style="color:var(--muted)"><?= round($m['size'] / 1024) ?> KB · PDF</small></div>
      <?php endforeach; ?></div>
    <?php endif; ?>
    <div class="panel"><h3>Dúvidas e comentários</h3>
      <form id="comment-form" method="post" action="<?= e(url('comentar.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="lesson_id" value="<?= (int)$lesson['id'] ?>"><input type="hidden" name="parent_id" value="">
        <textarea name="body" required maxlength="2000" placeholder="Escreva sua dúvida ou comentário..."></textarea>
        <div class="hint" id="reply-to"></div><br><button class="btn btn-sm">Enviar</button>
      </form>
      <?php foreach ($top as $cm) render_comment($cm, $replies); ?>
      <?php if (!$top): ?><p class="hint" style="margin-top:14px">Seja o primeiro a comentar.</p><?php endif; ?>
    </div>
  </div>
  <aside class="side">
    <h3><?= e($course['title']) ?></h3>
    <div class="bar"><i style="width:<?= $prog['pct'] ?>%"></i></div>
    <small id="pct-label" style="padding:0 18px;color:var(--muted)"><?= $prog['pct'] ?>% concluído</small>
    <?php foreach ($mods as $m): ?>
    <details class="module" <?= $m['id'] == $lesson['module_id'] ? 'open' : '' ?>>
      <summary><?= e($m['title']) ?></summary>
      <?php foreach ($bySec[$m['id']] ?? [] as $l):
          $can = $isOwner ? ($openMap[$l['id']] ?? false) : (bool)$l['is_free']; ?>
        <a class="lesson-item <?= isset($done[$l['id']]) ? 'done' : '' ?> <?= $l['id'] == $lesson['id'] ? 'current' : '' ?>"
           href="<?= e(url($can ? 'aula.php?id=' . $l['id'] : 'curso.php?id=' . $cid)) ?>">
          <span class="dot"><?= $can ? (isset($done[$l['id']]) ? '&#10003;' : '') : '&#128274;' ?></span>
          <span class="t"><?= e($l['title']) ?><?php if (lesson_is_live($l) && $l['starts_at']): ?><small class="live-mini">&#128308; <?= e(live_short($l['starts_at'])) ?></small><?php elseif (!empty($l['release_at']) && strtotime((string)$l['release_at']) > time()): ?><small class="live-mini">&#128197; libera <?= e(live_short($l['release_at'])) ?></small><?php endif; ?><?php if ($isOwner): $lp = isset($done[$l['id']]) ? 100 : (int)($pcts[$l['id']] ?? 0); ?><span class="lbar"><i style="width:<?= $lp ?>%"></i></span><?php endif; ?></span>
          <?php if ($isOwner): ?><small class="lpct"><?= $lp ?>%</small><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </details>
    <?php endforeach; ?>
  </aside>
</div>
<?php require ROOT . '/app/views/footer.php'; ?>
