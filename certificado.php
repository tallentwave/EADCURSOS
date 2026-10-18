<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$c = course_by_id((int)($_GET['curso'] ?? 0));
if (!$c || !has_access($uid, (int)$c['id'])) { http_response_code(403); exit('Sem acesso.'); }
$cert = q_one('SELECT * FROM certificates WHERE user_id = ? AND course_id = ?', [$uid, $c['id']]);
if (!$cert) { // certificados já emitidos continuam válidos mesmo se provas forem criadas depois
    [$eligible, $why] = certificate_eligible($uid, (int)$c['id']);
    if (!$eligible) { flash('err', 'Certificado indisponível: ' . $why); redirect('curso.php?id=' . $c['id']); }
}
$fe = final_exam((int)$c['id']);
$grade = $fe && exam_ready($fe) ? exam_best($uid, (int)$fe['id']) : null;

$cert = q_one('SELECT * FROM certificates WHERE user_id = ? AND course_id = ?', [$uid, $c['id']]);
if (!$cert) {
    $code = strtoupper(bin2hex(random_bytes(6)));
    q('INSERT INTO certificates (user_id, course_id, code, issued_at) VALUES (?, ?, ?, ?)', [$uid, $c['id'], $code, now()]);
    $cert = q_one('SELECT * FROM certificates WHERE user_id = ? AND course_id = ?', [$uid, $c['id']]);
}
$hours = (int)q_val('SELECT COALESCE(SUM(l.duration_min),0) FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = ?', [$c['id']]);
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificado · <?= e($c['title']) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body class="cert-page">
<div class="cert">
  <div class="org"><?= e(site_name()) ?></div>
  <h1>Certificado de Conclusão</h1>
  <small>Certificamos que</small>
  <div><span class="name"><?= e($u['name']) ?></span></div>
  <small>concluiu com êxito o curso</small>
  <div class="course"><?= e($c['title']) ?></div>
  <small><?= $hours > 0 ? 'Carga horária: ' . round($hours / 60, 1) . ' h · ' : '' ?><?= $grade !== null ? 'Aproveitamento na avaliação final: ' . rtrim(rtrim(number_format($grade, 2, ',', ''), '0'), ',') . '% · ' : '' ?>Emitido em <?= e(date('d/m/Y', strtotime($cert['issued_at']))) ?></small>
  <small>Código de verificação: <strong style="color:var(--gold)"><?= e($cert['code']) ?></strong> · <?= e(url('verificar.php')) ?></small>
</div>
<div class="no-print"><button class="btn" onclick="window.print()">Imprimir / Salvar PDF</button><a class="btn btn-ghost" style="color:#333" href="<?= e(url('curso.php?id=' . $c['id'])) ?>">Voltar</a></div>
</body></html>
