<?php
// Porta de entrada da aula ao vivo: o link externo da sala nunca fica no HTML. Aqui conferimos matrícula e horário,
// registramos a presença (que conclui a aula) e só então redirecionamos para a sala.
require __DIR__ . '/app/bootstrap.php';
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
$u = require_login();
$uid = (int)$u['id'];
$l = q_one('SELECT l.*, m.course_id FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$l || !lesson_is_live($l)) { http_response_code(404); exit('Aula não encontrada.'); }
$cid = (int)$l['course_id'];
$isAdmin = $u['role'] === 'admin';
$back = 'aula.php?id=' . (int)$l['id'];
$course = course_by_id($cid);
if (!$course || ($course['status'] !== 'published' && !$isAdmin)) { http_response_code(404); exit('Curso indisponível.'); }

$owner = has_access($uid, $cid);
if (!$owner && !$l['is_free']) { flash('err', 'Você ainda não tem acesso a este curso.'); redirect('curso.php?id=' . $cid); }
if ($owner && !$isAdmin && !(open_map($uid, $cid)[(int)$l['id']] ?? false)) { flash('err', 'Conclua as aulas anteriores para liberar esta.'); redirect('curso.php?id=' . $cid); }

$url = safe_live_url((string)$l['live_url']);
if (!$url) { flash('info', 'O professor ainda não divulgou o link da sala. Volte mais perto do horário.'); redirect($back); }
if (!$isAdmin && !live_can_join($l)) {
    $t = live_times($l);
    flash('err', live_state($l) === 'ended' ? 'Esta aula ao vivo já foi encerrada.' : 'A sala abre 15 minutos antes do início (' . ($t ? live_short(date('Y-m-d H:i:s', $t['start'])) : '') . ').');
    redirect($back);
}
if (!$isAdmin) live_mark_attended($uid, (int)$l['id']);
header('Location: ' . $url);
exit;
