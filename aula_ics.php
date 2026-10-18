<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$l = q_one('SELECT l.*, m.course_id FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$l || !lesson_is_live($l) || !live_times($l)) { http_response_code(404); exit('Aula não encontrada.'); }
if (!has_access((int)$u['id'], (int)$l['course_id']) && !$l['is_free']) { http_response_code(403); exit('Sem acesso.'); }
$course = course_by_id((int)$l['course_id']);
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="aula-ao-vivo.ics"');
header('Cache-Control: private, no-store');
echo live_ics($l, (string)$course['title'], url('aula.php?id=' . $l['id']));
