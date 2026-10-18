<?php
require __DIR__ . '/app/bootstrap.php';
$u = current_user();           // página pública: visitantes podem ver a apresentação do curso
$uid = $u ? (int)$u['id'] : 0;
$c = course_by_id((int)($_GET['id'] ?? 0));
if (!$c || ($c['status'] !== 'published' && (!$u || $u['role'] !== 'admin'))) { http_response_code(404); exit('Curso não encontrado.'); }
$cid = (int)$c['id'];
$owned = has_access($uid, $cid);

$mods = q_all('SELECT * FROM modules WHERE course_id = ? ORDER BY position, id', [$cid]);
$lessonsBy = [];
foreach (course_lessons($cid) as $l) $lessonsBy[$l['module_id']][] = $l;
$doneSet = $uid ? array_flip(array_column(q_all('SELECT lesson_id FROM progress WHERE user_id = ? AND completed = 1', [$uid]), 'lesson_id')) : [];
$prog = $uid ? course_progress($uid, $cid) : ['total' => (int)q_val('SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = ?', [$cid]), 'done' => 0, 'pct' => 0];
$pcts = $owned ? lesson_pcts($uid, $cid) : [];
$nextUrl = 'curso.php?id=' . $cid;
$openMap = $owned ? open_map($uid, $cid) : [];
$nl = $owned ? next_lesson($uid, $cid) : null;
$pending = !$owned && $uid ? q_one('SELECT id FROM orders WHERE user_id = ? AND course_id = ? AND status = "pending" ORDER BY id DESC LIMIT 1', [$uid, $cid]) : null;

$title = $c['title'];
require ROOT . '/app/views/header.php';
?>
<section class="course-hero" style="background-image:url('<?= e(img_url($c['banner'] ?: $c['cover'])) ?>')">
  <div>
    <span class="tag" style="color:var(--gold)"><?= e($c['category']) ?></span>
    <h1><?= e($c['title']) ?></h1>
    <p><?= e($c['short_desc']) ?></p>
    <?php $allCount = array_sum(array_map('count', $lessonsBy)); $liveCount = count(array_filter(array_merge(...array_values($lessonsBy ?: [[]])), 'lesson_is_live')); ?>
    <div class="meta"><?= count($mods) ?> <?= count($mods) === 1 ? 'módulo' : 'módulos' ?> · <?= $allCount ?> <?= $allCount === 1 ? 'aula' : 'aulas' ?><?= $liveCount ? ' · ' . $liveCount . ' ao vivo' : '' ?></div>
    <?php if ($owned): ?>
      <a class="btn" href="<?= e(url($nl ? 'aula.php?id=' . $nl['id'] : 'home.php')) ?>">&#9654; <?= $prog['done'] ? 'Continuar curso' : 'Começar curso' ?></a>
      <?php [$certOk, $certWhy] = certificate_eligible($uid, $cid); $hasFinal = exam_ready(final_exam($cid)); ?>
      <?php if ($certOk): ?><a class="btn btn-blue" href="<?= e(url('certificado.php?curso=' . $cid)) ?>">&#127942; Emitir certificado</a>
      <a class="btn btn-blue" href="<?= e(url('carteirinha.php?curso=' . $cid)) ?>">&#129706; Carteirinha</a>
      <?php elseif ($hasFinal || required_module_exams($cid)): ?><div class="meta">&#127942; Certificado: <?= e($certWhy) ?></div><?php endif; ?>
      <div class="bar" style="max-width:340px;margin-top:16px"><i style="width:<?= $prog['pct'] ?>%"></i></div>
      <div class="meta"><?= $prog['pct'] ?>% concluído</div>
    <?php elseif (!$u): ?>
      <span class="big-price"><?= (float)$c['price'] > 0 ? money($c['price']) : 'Grátis' ?></span>
      <a class="btn" href="<?= e(url('login.php?next=' . urlencode(BASE_URL_PATH . '/' . $nextUrl))) ?>"><?= (float)$c['price'] > 0 ? 'Entrar para comprar' : 'Entrar para começar' ?></a>
      <a class="btn btn-ghost" href="<?= e(url('registro.php?next=' . urlencode(BASE_URL_PATH . '/' . $nextUrl))) ?>">Criar conta</a>
      <p class="meta">Aulas marcadas como grátis podem ser assistidas depois de entrar. O restante é liberado após a compra.</p>
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
      <?php $lp = $done ? 100 : (int)($pcts[$l['id']] ?? 0); $dur = lesson_duration($l);
            $durTxt = $dur >= 60 ? fmt_workload((int)round($dur / 60)) : ($dur > 0 ? (int)$dur . ' s' : '');
            $isL = lesson_is_live($l); $relFuture = !empty($l['release_at']) && strtotime((string)$l['release_at']) > time();
            $badge = $isL ? ($l['starts_at'] ? '<span class="tag-s tag-bad live-tag">&#128308; AO VIVO · ' . e(live_short($l['starts_at'])) . '</span>' : '<span class="tag-s tag-bad live-tag">&#128308; AO VIVO</span>')
                          : ($relFuture ? '<span class="tag-s tag-warn live-tag">&#128197; Libera ' . e(live_short($l['release_at'])) . '</span>' : '');
            if ($isL && $l['starts_at']) $durTxt = ''; ?>
      <?php if ($can): ?>
      <a class="lesson-item <?= $done ? 'done' : '' ?>" href="<?= e(url('aula.php?id=' . $l['id'])) ?>">
        <span class="dot"><?= $done ? '&#10003;' : '' ?></span>
        <span class="t"><?= e($l['title']) ?> <?= $badge ?><?php if ($owned): ?><span class="lbar"><i style="width:<?= $lp ?>%"></i></span><?php endif; ?></span>
        <small><?= $l['is_free'] && !$owned ? 'Grátis · ' : '' ?><?= e($durTxt) ?><?= $owned ? ' · <b class="lpctb">' . $lp . '%</b>' : '' ?></small>
      </a>
      <?php else: ?>
      <div class="lesson-item locked" title="<?= $owned ? 'Assista a aula anterior até o final para liberar' : 'Adquira o curso para liberar' ?>"><span class="dot">&#128274;</span>
        <span class="t"><?= e($l['title']) ?> <?= $badge ?><?php if ($owned): ?><span class="lbar"><i style="width:<?= $lp ?>%"></i></span><?php endif; ?></span>
        <small><?= e($durTxt) ?><?= $owned ? ' · <b class="lpctb">' . $lp . '%</b>' : '' ?></small></div>
      <?php endif; ?>
    <?php endforeach; ?>
    <?= exam_row_html(module_exam((int)$m['id']), $owned, $uid) ?>
  </details>
  <?php endforeach; ?>
  <?php $fe = final_exam($cid); if (exam_ready($fe)): ?><details class="module" open><summary>Avaliação final</summary><?= exam_row_html($fe, $owned, $uid) ?></details><?php endif; ?>
</div>
<?php require ROOT . '/app/views/footer.php'; ?>
