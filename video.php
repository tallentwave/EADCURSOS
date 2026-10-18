<?php
// Entrega a fonte do vídeo só para quem tem acesso e a aula liberada: o link nunca aparece no HTML da página.
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, private');
$u = current_user();
if (!$u) json_fail(401, 'Faça login.');
[$lesson, $mode] = watch_guard($u, (int)($_GET['id'] ?? 0));
$src = video_source((string)$lesson['video_url']);
if (!$src) json_fail(404, 'Vídeo indisponível.');
$row = q_one('SELECT progress_sec FROM lesson_watch WHERE user_id = ? AND lesson_id = ?', [$u['id'], $lesson['id']]);
$dur = lesson_duration($lesson);
$done = (bool)q_val('SELECT 1 FROM progress WHERE user_id = ? AND lesson_id = ? AND completed = 1', [$u['id'], $lesson['id']]);
echo json_encode($src + [
    'ok' => true,
    'bypass' => $mode !== '',           // admin/degustação: sem trava
    'start' => $row ? floor((float)$row['progress_sec']) : 0,
    'done' => $done,
    'dur' => $dur,
    'pct' => watch_pct($row ? (float)$row['progress_sec'] : 0.0, $dur, $done),
]);
