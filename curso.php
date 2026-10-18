<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$c = course_by_id((int)($_GET['id'] ?? 0));
if (!$c || ($c['status'] !== 'published' && $u['role'] !== 'admin')) { http_response_code(404); exit('Curso não encontrado.'); }
$cid = (int)$c['id'];
$owned = has_access($uid, $cid);

$mods = q_all('SELECT * FROM modules WHERE course_id = ? ORDER BY position, id', [$cid]);
$lessonsBy = [];
foreach (course_lessons($cid) as $l) $lessonsBy[$l['module_id']][] = $l;
$doneSet = array_flip(array_column(q_all('SELECT lesson_id FROM progress WHERE user_id = ? AND completed = 1', [$uid]), 'lesson_id'));
$prog = course_progress($uid, $cid);
$openMap = $owned ? open_map($uid, $cid) : [];
$nl = $owned ? next_lesson($uid, $cid) : null;
$pending = !$owned ? q_one('SELECT id FROM orders WHERE user_id = ? AND course_id = ? AND status = "pending" ORDER BY id DESC LIMIT 1', [$uid, $cid]) : null;

$title = $c['title'];
require ROOT . '/app/views/header.php';
?>
<section class="course-hero" style="background-image:url('<?= e(img_url($c['banner'] ?: $c['cover'])) ?>')">
  <div>
    <span class="tag" style="color:var(--gold)"><?= e($c['category']) ?></span>
    <h1><?= e($c['title']) ?></h1>
    <p><?= e($c['short_desc']) ?></p>
    <div class="meta"><?= count($mods) ?> módulos · <?= $prog['total'] ?> aulas</div>
    <?php if ($owned): ?>
      <a class="btn" href="<?= e(url($nl ? 'aula.php?id=' . $nl['id'] : 'home.php')) ?>">&#9654; <?= $prog['done'] ? 'Continuar curso' : 'Começar curso' ?></a>
      <?php if ($prog['pct'] === 100): ?><a class="btn btn-blue" href="<?= e(url('certificado.php?curso=' . $cid)) ?>">&#127942; Emitir certificado</a><?php endif; ?>
      <div class="bar" style="max-width:340px;margin-top:16px"><i style="width:<?= $prog['pct'] ?>%"></i></div>
      <div class="meta"><?= $prog['pct'] ?>% concluído</div>
    <?php else: ?>
      <form method="post" action="<?= e(url('comprar.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= $cid ?>">
        <span class="big-price" id="price-box" data-price="<?= e(money($c['price'])) ?>"><?= (float)$c['price'] > 0 ? money($c['price']) : 'Grátis' ?></span>
        <?php if ((float)$c['price'] > 0): ?>
        <div class="coupon-row"><input type="text" name="coupon" id="coupon" maxlength="40" placeholder="Cupom de desconto" autocomplete="off" style="max-width:220px;text-transform:uppercase">
          <button type="button" class="btn btn-ghost btn-sm" id="btn-coupon" data-url="<?= e(url('cupom.php')) ?>" data-course="<?= $cid ?>">Aplicar</button> <small id="coupon-msg"></small></div>
        <?php endif; ?>
        <button class="btn"><?= (float)$c['price'] > 0 ? 'Comprar agora (PIX, cartão ou boleto)' : 'Liberar acesso grátis' ?></button>
      </form>
      <p class="meta">Tem um voucher? <a href="<?= e(url('resgatar.php')) ?>">Resgate aqui</a>.</p>
      <?php if ($pending): ?><p class="meta">Você tem um pedido aguardando pagamento. Assim que for aprovado o acesso é liberado automaticamente.</p><?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<div class="container" style="max-width:900px">
  <?php if (trim((string)$c['description']) !== ''): ?>
    <div class="panel"><h3>Sobre o curso</h3><p style="white-space:pre-wrap"><?= e($c['description']) ?></p></div>
  <?php endif; ?>
  <h2 style="margin:28px 0 14px;color:var(--gold)">Conteúdo</h2>
  <?php foreach ($mods as $i => $m): ?>
  <details class="module" <?= $i === 0 ? 'open' : '' ?>>
    <summary><?= e($m['title']) ?> <small style="color:var(--muted)">· <?= count($lessonsBy[$m['id']] ?? []) ?> aulas</small></summary>
    <?php foreach ($lessonsBy[$m['id']] ?? [] as $l):
        $can = $owned ? ($openMap[$l['id']] ?? false) : (bool)$l['is_free'];
        $done = isset($doneSet[$l['id']]); ?>
      <?php if ($can): ?>
      <a class="lesson-item <?= $done ? 'done' : '' ?>" href="<?= e(url('aula.php?id=' . $l['id'])) ?>">
        <span class="dot"><?= $done ? '&#10003;' : '' ?></span><span class="t"><?= e($l['title']) ?></span>
        <small><?= $l['is_free'] && !$owned ? 'Grátis · ' : '' ?><?= $l['duration_min'] ? (int)$l['duration_min'] . ' min' : '' ?></small>
      </a>
      <?php else: ?>
      <div class="lesson-item" style="opacity:.6" title="<?= $owned ? 'Assista a aula anterior para liberar' : 'Compre o curso para liberar' ?>"><span class="dot">&#128274;</span><span class="t"><?= e($l['title']) ?></span><small><?= $l['duration_min'] ? (int)$l['duration_min'] . ' min' : '' ?></small></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </details>
  <?php endforeach; ?>
</div>
<?php require ROOT . '/app/views/footer.php'; ?>
