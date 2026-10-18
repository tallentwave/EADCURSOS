<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
require_post();
$lesson = q_one('SELECT l.id, l.is_free, m.course_id FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = ?', [(int)($_POST['lesson_id'] ?? 0)]);
if (!$lesson || (!has_access((int)$u['id'], (int)$lesson['course_id']) && !$lesson['is_free'])) { http_response_code(403); exit('Sem acesso.'); }
$body = trim((string)($_POST['body'] ?? ''));
$parent = (int)($_POST['parent_id'] ?? 0) ?: null;
if ($parent && !q_val('SELECT 1 FROM comments WHERE id = ? AND lesson_id = ? AND parent_id IS NULL', [$parent, $lesson['id']])) $parent = null;
if ($body === '' || mb_strlen($body) > 2000) { flash('err', 'Comentário inválido.'); redirect('aula.php?id=' . $lesson['id']); }
$recent = (int)q_val('SELECT COUNT(*) FROM comments WHERE user_id = ? AND created_at > ?', [$u['id'], date('Y-m-d H:i:s', time() - 60)]);
if ($recent >= 5) { flash('err', 'Aguarde um instante antes de comentar novamente.'); redirect('aula.php?id=' . $lesson['id']); }
q('INSERT INTO comments (lesson_id, user_id, parent_id, body, created_at) VALUES (?, ?, ?, ?, ?)', [$lesson['id'], $u['id'], $parent, $body, now()]);
flash('ok', 'Comentário publicado.');
redirect('aula.php?id=' . $lesson['id'] . '#comment-form');
