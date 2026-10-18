<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$e = q_one('SELECT * FROM exams WHERE id = ?', [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
if (!$e || !$e['active']) { http_response_code(404); exit('Prova não encontrada.'); }
$cid = (int)$e['course_id'];
$course = course_by_id($cid);
$isAdmin = $u['role'] === 'admin';
if (!$course || ($course['status'] !== 'published' && !$isAdmin)) { http_response_code(404); exit('Curso indisponível.'); }
$self = 'prova.php?id=' . $e['id'];
$block = exam_prereq($uid, $e);
$module = $e['module_id'] !== null ? q_one('SELECT * FROM modules WHERE id = ?', [$e['module_id']]) : null;
$qtotal = exam_qcount((int)$e['id']);
$perAttempt = (int)$e['draw'] > 0 ? min((int)$e['draw'], $qtotal) : $qtotal;
$max = (int)$e['max_attempts'];

// tentativa aberta (se expirou, é encerrada com nota 0)
$open = q_one('SELECT * FROM exam_attempts WHERE user_id = ? AND exam_id = ? AND finished_at IS NULL ORDER BY id DESC LIMIT 1', [$uid, $e['id']]);
if ($open && ($dl = attempt_deadline($e, $open)) !== null && time() > $dl + EXAM_GRACE_SEC) {
    exam_finish($open, $e, []);
    flash('err', 'O tempo da prova esgotou. A tentativa foi encerrada.');
    redirect($self);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'start') {
        if ($block !== '') { flash('err', $block); redirect($self); }
        if ($qtotal === 0) { flash('err', 'Esta prova ainda não tem questões.'); redirect($self); }
        if (!$open) {
            if ($max > 0 && exam_attempts_used($uid, (int)$e['id']) >= $max) { flash('err', 'Você esgotou as tentativas desta prova.'); redirect($self); }
            exam_start($uid, $e);
        }
        redirect($self);
    }
    if ($action === 'submit' && $open && (int)($_POST['attempt'] ?? 0) === (int)$open['id']) {
        $done = exam_finish($open, $e, (array)($_POST['a'] ?? []));
        if ($done && $done['passed'] && $e['module_id'] === null) {
            [$ok] = certificate_eligible($uid, $cid);
            if ($ok) mail_send($u['email'], 'Certificado disponível: ' . $course['title'], "Parabéns, {$u['name']}!\n\nVocê foi aprovado(a) na avaliação final de \"{$course['title']}\" e já pode emitir seu certificado:\n" . url('certificado.php?curso=' . $cid));
        }
        redirect($self . '&r=' . (int)$open['id']);
    }
    redirect($self);
}

$result = null;
if (isset($_GET['r'])) {
    $result = q_one('SELECT * FROM exam_attempts WHERE id = ? AND user_id = ? AND exam_id = ? AND finished_at IS NOT NULL', [(int)$_GET['r'], $uid, $e['id']]);
}
$history = q_all('SELECT * FROM exam_attempts WHERE user_id = ? AND exam_id = ? AND finished_at IS NOT NULL ORDER BY id DESC', [$uid, $e['id']]);
$used = exam_attempts_used($uid, (int)$e['id']);
$passedNow = exam_passed($uid, (int)$e['id']);
$best = exam_best($uid, (int)$e['id']);

$title = $e['title'];
$withExam = (bool)$open;
require ROOT . '/app/views/header.php';
?>
<div class="container" style="max-width:820px">
  <small style="color:var(--muted)"><a href="<?= e(url('curso.php?id=' . $cid)) ?>">&laquo; <?= e($course['title']) ?></a><?= $module ? ' · ' . e($module['title']) : '' ?></small>
  <h1 style="color:var(--gold);margin:6px 0 4px">&#128221; <?= e($e['title']) ?></h1>
  <p class="hint"><?= $module ? 'Prova do módulo' : 'Avaliação final do curso' ?> · nota mínima <?= (int)$e['min_score'] ?>% · <?= $perAttempt ?> questão(ões)
    · <?= $max > 0 ? $max . ' tentativa(s)' : 'tentativas ilimitadas' ?><?= (int)$e['time_limit_min'] > 0 ? ' · tempo: ' . (int)$e['time_limit_min'] . ' min' : '' ?></p>

<?php if ($result): $ok = (bool)$result['passed']; $lay = json_decode($result['layout'], true) ?: []; $ans = json_decode((string)$result['answers'], true) ?: [];
    $rightIds = []; if ($lay) foreach (q_all('SELECT question_id, id FROM exam_options WHERE is_correct = 1 AND question_id IN (' . implode(',', array_map('intval', array_column($lay, 'q'))) . ')') as $r) $rightIds[(int)$r['question_id']] = (int)$r['id']; ?>
  <div class="panel" style="text-align:center;border-color:<?= $ok ? 'var(--ok)' : 'var(--danger)' ?>">
    <div style="font:700 3rem Georgia,serif;color:<?= $ok ? 'var(--ok)' : 'var(--danger)' ?>"><?= rtrim(rtrim(number_format((float)$result['score'], 2, ',', ''), '0'), ',') ?>%</div>
    <h2><?= $ok ? 'Aprovado(a)! &#127881;' : ($result['timed_out'] ? 'Tempo esgotado' : 'Não atingiu a nota mínima') ?></h2>
    <?php if ($ok && $module): ?><p style="margin:10px 0"><a class="btn" href="<?= e(url('curso.php?id=' . $cid)) ?>">Continuar o curso</a></p>
    <?php elseif ($ok): [$cert] = certificate_eligible($uid, $cid); ?><p style="margin:10px 0"><?php if ($cert): ?><a class="btn" href="<?= e(url('certificado.php?curso=' . $cid)) ?>">&#127942; Emitir meu certificado</a><?php else: ?><span class="hint">Para o certificado, falta concluir os demais requisitos do curso.</span><?php endif; ?></p>
    <?php elseif ($max === 0 || $used < $max): ?><p style="margin:10px 0" class="hint">Revise as aulas e tente novamente<?= $max > 0 ? ' (restam ' . ($max - $used) . ' tentativa(s))' : '' ?>.</p>
    <?php else: ?><p style="margin:10px 0" class="hint">Tentativas esgotadas. Fale com o suporte para liberar uma nova tentativa.</p><?php endif; ?>
  </div>
  <div class="panel"><h3>Seu gabarito</h3><p class="hint">Mostramos apenas se cada resposta estava certa ou errada.</p>
  <?php foreach ($lay as $i => $row): $good = isset($ans[$row['q']]) && ($rightIds[$row['q']] ?? 0) === (int)$ans[$row['q']] && !$result['timed_out']; ?>
    <div class="mat"><span style="color:<?= $good ? 'var(--ok)' : 'var(--danger)' ?>;font-weight:700;width:24px"><?= $good ? '&#10003;' : '&#10007;' ?></span><span>Questão <?= $i + 1 ?></span></div>
  <?php endforeach; ?></div>

<?php elseif ($open):
    $lay = json_decode($open['layout'], true) ?: [];
    $qids = array_column($lay, 'q');
    $qmap = array_column(q_all('SELECT id, body FROM exam_questions WHERE id IN (' . implode(',', array_map('intval', $qids)) . ')'), 'body', 'id');
    $omap = array_column(q_all('SELECT id, body FROM exam_options WHERE question_id IN (' . implode(',', array_map('intval', $qids)) . ')'), 'body', 'id');
    $dl = attempt_deadline($e, $open); ?>
  <?php if ($dl): ?><div class="exam-timer" id="exam-timer">Tempo restante: <b id="exam-left">--:--</b></div><?php endif; ?>
  <form method="post" id="exam-form" data-remaining="<?= $dl ? max(0, $dl - time()) : 0 ?>" data-msg="Enviar suas respostas? Não será possível alterar depois.">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><input type="hidden" name="action" value="submit"><input type="hidden" name="attempt" value="<?= (int)$open['id'] ?>">
    <?php foreach ($lay as $i => $row): ?>
    <fieldset class="question"><legend>Questão <?= $i + 1 ?> de <?= count($lay) ?></legend>
      <p style="white-space:pre-wrap;margin-bottom:10px"><?= e($qmap[$row['q']] ?? '') ?></p>
      <?php foreach ($row['o'] as $oid): ?><label class="opt"><input type="radio" name="a[<?= (int)$row['q'] ?>]" value="<?= (int)$oid ?>"> <span><?= e($omap[$oid] ?? '') ?></span></label><?php endforeach; ?>
    </fieldset>
    <?php endforeach; ?>
    <button class="btn" id="exam-submit">Enviar respostas</button>
  </form>

<?php else: ?>
  <?php if ($passedNow): ?><div class="alert alert-ok" style="margin:14px 0">Você já foi aprovado(a) nesta prova<?= $best !== null ? ' com ' . rtrim(rtrim(number_format($best, 2, ',', ''), '0'), ',') . '%' : '' ?>.</div><?php endif; ?>
  <?php if ($block !== ''): ?><div class="alert alert-info" style="margin:14px 0"><?= e($block) ?></div>
  <?php elseif ($qtotal === 0): ?><div class="alert alert-info" style="margin:14px 0">Esta prova ainda não tem questões.</div>
  <?php elseif ($max > 0 && $used >= $max && !$passedNow): ?><div class="alert alert-err" style="margin:14px 0">Você esgotou as tentativas. Fale com o suporte para liberar uma nova tentativa.</div>
  <?php else: ?>
  <div class="panel"><h3>Antes de começar</h3>
    <ul style="margin:0 0 14px 20px;color:var(--muted)">
      <li>Cada questão tem uma única alternativa correta.</li><li>As questões e alternativas aparecem em ordem aleatória.</li>
      <?php if ((int)$e['time_limit_min'] > 0): ?><li>Você terá <?= (int)$e['time_limit_min'] ?> minuto(s). Ao terminar o tempo a prova é encerrada com nota 0 se não enviada.</li><?php endif; ?>
      <li>Depois de iniciar, a tentativa conta mesmo que você saia da página.</li>
    </ul>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><input type="hidden" name="action" value="start"><button class="btn"><?= $passedNow ? 'Refazer prova' : 'Iniciar prova' ?></button></form>
  </div>
  <?php endif; ?>
  <?php if ($history): ?><div class="panel"><h3>Tentativas anteriores</h3>
    <div class="table-wrap"><table><tr><th>Data</th><th>Nota</th><th>Resultado</th></tr>
    <?php foreach ($history as $h): ?><tr><td><?= e(date('d/m/Y H:i', strtotime($h['finished_at']))) ?></td><td><?= rtrim(rtrim(number_format((float)$h['score'], 2, ',', ''), '0'), ',') ?>%</td>
      <td><span class="tag-s tag-<?= $h['passed'] ? 'ok' : 'bad' ?>"><?= $h['passed'] ? 'Aprovado' : ($h['timed_out'] ? 'Tempo esgotado' : 'Reprovado') ?></span></td></tr><?php endforeach; ?></table></div></div><?php endif; ?>
<?php endif; ?>
</div>
<?php require ROOT . '/app/views/footer.php'; ?>
