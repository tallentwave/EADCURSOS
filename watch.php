<?php
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, private');
$u = current_user();
if (!$u) json_fail(401, 'Faça login.');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail(405, 'Método inválido.');
csrf_check();
[$lesson, $mode] = watch_guard($u, (int)($_POST['lesson_id'] ?? 0));
$clientDur = (float)($_POST['dur'] ?? 0);

if ($mode === 'admin') {
    // o admin assistindo grava a duração exata do vídeo (usada como referência dos alunos)
    if ((int)$lesson['duration_sec'] === 0 && $clientDur >= 5 && $clientDur <= 86400) {
        q('UPDATE lessons SET duration_sec = ? WHERE id = ?', [(int)round($clientDur), $lesson['id']]);
    }
    echo json_encode(['ok' => true, 'done' => true, 'pct' => 100, 'bypass' => true]); exit;
}
if ($mode === 'free') { echo json_encode(['ok' => true, 'done' => true, 'pct' => 100, 'bypass' => true]); exit; }

$r = watch_ping((int)$u['id'], $lesson, (float)($_POST['pos'] ?? 0), ($_POST['playing'] ?? '0') === '1', $clientDur);
echo json_encode(['ok' => true, 'done' => $r['done'], 'pct' => $r['pct'], 'course_pct' => course_progress((int)$u['id'], (int)$lesson['course_id'])['pct']]);
