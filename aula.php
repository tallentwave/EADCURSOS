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
if ($isOwner && !($openMap[(int)$lesson['id']] ?? false)) {
    flash('err', 'Assista pelo menos 90% da aula anterior para liberar esta.');
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
$embed = video_embed($lesson['video_url']);
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
require ROOT . '/app/views/header.php';
?>
<div class="player-layout">
  <div>
    <?php if ($embed): ?>
      <div class="video" id="player-wrap"><div class="stage" id="player" data-lesson="<?= (int)$lesson['id'] ?>" data-src="<?= e(url('video.php?id=' . $lesson['id'])) ?>" data-ping="<?= e(url('watch.php')) ?>"><div class="msg">Carregando vídeo...</div></div></div>
      <div class="watchbar" id="watchbar"><div class="track"><i id="watch-fill" style="width:<?= $isDone ? 100 : 0 ?>%"></i></div><span id="watch-status"><?= $isDone ? '&#10003; Aula concluída' : ($isOwner ? 'Assista pelo menos 90% para liberar a próxima aula' : 'Aula de degustação') ?></span></div>
    <?php else: ?>
      <div class="video empty">Vídeo não disponível para esta aula.</div>
    <?php endif; ?>
    <div class="lesson-head">
      <div><small style="color:var(--muted)"><?= e($lesson['module_title']) ?></small><h1><?= e($lesson['title']) ?></h1></div>
      <?php if (!$embed): ?>
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
      <?php if ($prog['pct'] === 100 && has_access($uid, $cid)): ?><a class="btn btn-blue btn-sm" href="<?= e(url('certificado.php?curso=' . $cid)) ?>">&#127942; Certificado</a><?php endif; ?>
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
          <span class="dot"><?= $can ? (isset($done[$l['id']]) ? '&#10003;' : '') : '&#128274;' ?></span><span class="t"><?= e($l['title']) ?></span>
        </a>
      <?php endforeach; ?>
    </details>
    <?php endforeach; ?>
  </aside>
</div>
<?php require ROOT . '/app/views/footer.php'; ?>
