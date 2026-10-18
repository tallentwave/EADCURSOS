<?php
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json');
$u = current_user();
if (!$u || $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(403); echo '{"ok":false}'; exit; }
csrf_check();
$lesson = q_one('SELECT l.id, l.is_free, m.course_id FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = ?', [(int)($_POST['lesson_id'] ?? 0)]);
if (!$lesson || (!has_access((int)$u['id'], (int)$lesson['course_id']) && !$lesson['is_free'])) { http_response_code(403); echo '{"ok":false}'; exit; }
$done = ($_POST['completed'] ?? '0') === '1' ? 1 : 0;
q('INSERT INTO progress (user_id, lesson_id, completed, updated_at) VALUES (?, ?, ?, ?)
   ON DUPLICATE KEY UPDATE completed = VALUES(completed), updated_at = VALUES(updated_at)', [$u['id'], $lesson['id'], $done, now()]);
echo json_encode(['ok' => true, 'pct' => course_progress((int)$u['id'], (int)$lesson['course_id'])['pct']]);
