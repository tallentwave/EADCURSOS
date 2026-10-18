<?php
require __DIR__ . '/app/bootstrap.php';
// Vitrine pública: qualquer visitante vê os cursos; ao entrar, aparecem progresso e "continue assistindo".
$u = current_user();
$uid = $u ? (int)$u['id'] : 0;

$courses = q_all('SELECT * FROM courses WHERE status = "published" ORDER BY featured DESC, id DESC');
$owned = [];
$progress = [];
if ($uid) {
    foreach ($courses as $c) if (has_access($uid, (int)$c['id'])) $owned[(int)$c['id']] = true;
    foreach ($owned as $cid => $_) $progress[$cid] = course_progress($uid, $cid)['pct'];
}

// "Continuar assistindo": cursos com progresso iniciado e não concluído, mais recente primeiro
$continue = [];
if ($uid) {
    $last = q_all('SELECT m.course_id, MAX(p.updated_at) AS last FROM progress p
                   JOIN lessons l ON l.id = p.lesson_id JOIN modules m ON m.id = l.module_id
                   WHERE p.user_id = ? GROUP BY m.course_id ORDER BY last DESC', [$uid]);
    $byId = array_column($courses, null, 'id');
    foreach ($last as $r) {
        $cid = (int)$r['course_id'];
        if (isset($owned[$cid], $byId[$cid]) && $progress[$cid] < 100) {
            $c = $byId[$cid];
            $nl = next_lesson($uid, $cid);
            if ($nl) $c['next_lesson_id'] = $nl['id'];
            $continue[] = $c;
        }
    }
}

$hero = $courses[0] ?? null;
foreach ($courses as $c) if ($c['featured']) { $hero = $c; break; }

$mine = [];
foreach ($courses as $c) {
    if (isset($owned[(int)$c['id']])) {
        $nl = next_lesson($uid, (int)$c['id']);
        if ($nl) $c['next_lesson_id'] = $nl['id'];
        $mine[] = $c;
    }
}
$cats = [];
foreach ($courses as $c) $cats[$c['category']][] = $c;

$lives = $uid ? upcoming_lives($uid, $u['role'] === 'admin') : [];
$title = 'Cursos';
require ROOT . '/app/views/header.php';
?>
<?php if ($hero): $heroOwned = isset($owned[(int)$hero['id']]); ?>
<section class="hero" style="background-image:url('<?= e(img_url($hero['banner'] ?: $hero['cover'])) ?>')">
  <div class="hero-in">
    <span class="tag">Em destaque</span>
    <h1><?= e($hero['title']) ?></h1>
    <p><?= e($hero['short_desc']) ?></p>
    <div class="actions">
      <?php if ($heroOwned): $nl = next_lesson($uid, (int)$hero['id']); ?>
        <a class="btn" href="<?= e(url($nl ? 'aula.php?id=' . $nl['id'] : 'curso.php?id=' . $hero['id'])) ?>">&#9654; Assistir agora</a>
      <?php else: ?>
        <a class="btn" href="<?= e(url('curso.php?id=' . $hero['id'])) ?>"><?= (float)$hero['price'] > 0 ? 'Quero este curso · ' . money($hero['price']) : 'Começar grátis' ?></a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= e(url('curso.php?id=' . $hero['id'])) ?>">Mais informações</a>
      <?php if (!$u): ?><a class="btn btn-ghost" href="<?= e(url('login.php')) ?>">Já sou aluno</a><?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!$courses): ?>
  <div class="container"><div class="alert alert-info">Nenhum curso publicado ainda.</div></div>
<?php endif; ?>

<?php if ($lives): ?>
<section class="row"><h2>Próximas aulas ao vivo</h2><div class="live-cards">
  <?php foreach ($lives as $lv): $st = live_state($lv); $on = in_array($st, ['soon', 'live', 'late'], true); $ts = strtotime($lv['starts_at']); ?>
  <a class="live-card<?= $on ? ' on' : '' ?>" href="<?= e(url('aula.php?id=' . $lv['id'])) ?>">
    <span class="lc-date"><b><?= e(date('d', $ts)) ?></b><small><?= e(mb_strtoupper(mb_substr(CERT_MONTHS[(int)date('n', $ts) - 1], 0, 3))) ?></small></span>
    <span class="lc-info"><strong><?= e($lv['title']) ?></strong><small><?= e($lv['course_title']) ?></small><small><?= e(date('H:i', $ts)) ?> · <?= $on ? '<b class="lc-now">AO VIVO' . ($st === 'soon' ? ' · sala aberta' : '') . '</b>' : e(live_short($lv['starts_at'])) ?></small></span>
  </a>
  <?php endforeach; ?>
</div></section>
<?php endif; ?>

<?php if ($continue): ?>
<section class="row"><h2>Continue assistindo</h2><div class="scroller">
  <?php foreach ($continue as $c) echo course_card($c, true, $progress[(int)$c['id']]); ?>
</div></section>
<?php endif; ?>

<?php if ($mine): ?>
<section class="row" id="meus-cursos"><h2>Meus cursos</h2><div class="scroller">
  <?php foreach ($mine as $c) echo course_card($c, true, $progress[(int)$c['id']]); ?>
</div></section>
<?php endif; ?>

<?php foreach ($cats as $cat => $list):
    $show = array_filter($list, fn($c) => !isset($owned[(int)$c['id']]));
    if (!$show) continue; ?>
<section class="row"><h2><?= e($cat) ?><?= $uid ? ' · disponíveis' : '' ?></h2><div class="scroller">
  <?php foreach ($show as $c) echo course_card($c, false); ?>
</div></section>
<?php endforeach; ?>
<?php require ROOT . '/app/views/footer.php'; ?>
