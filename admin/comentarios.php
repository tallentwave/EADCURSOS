<?php
require __DIR__ . '/_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cm = q_one('SELECT * FROM comments WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    if ($cm && ($_POST['action'] ?? '') === 'reply' && trim($_POST['body'] ?? '') !== '') {
        $parent = $cm['parent_id'] ?: $cm['id'];
        q('INSERT INTO comments (lesson_id, user_id, parent_id, body, created_at) VALUES (?,?,?,?,?)', [$cm['lesson_id'], $admin['id'], $parent, mb_substr(trim($_POST['body']), 0, 2000), now()]);
        flash('ok', 'Resposta publicada.');
    } elseif ($cm && ($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM comments WHERE id = ?', [$cm['id']]); flash('ok', 'Comentário removido.');
    }
    back('comentarios.php');
}
$rows = q_all('SELECT c.*, u.name, u.role, l.title AS lesson, co.title AS course,
   (SELECT COUNT(*) FROM comments r WHERE r.parent_id = c.id AND r.user_id IN (SELECT id FROM users WHERE role="admin")) AS answered
   FROM comments c JOIN users u ON u.id = c.user_id JOIN lessons l ON l.id = c.lesson_id JOIN modules m ON m.id = l.module_id JOIN courses co ON co.id = m.course_id
   ORDER BY c.id DESC LIMIT 200');
require ROOT . '/app/views/admin_header.php';
?>
<h1>Comentários e dúvidas</h1>
<?php foreach ($rows as $r): ?>
<div class="formbox"><p style="margin-top:14px"><strong><?= e($r['name']) ?></strong><?= $r['role'] === 'admin' ? ' <span class="tag-s tag-warn">professor</span>' : '' ?>
 <small style="color:var(--muted)">· <?= e($r['course']) ?> › <?= e($r['lesson']) ?> · <?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?><?= $r['parent_id'] ? ' · resposta' : '' ?></small>
 <?php if (!$r['parent_id'] && $r['role'] !== 'admin'): ?> <span class="tag-s tag-<?= $r['answered'] ? 'ok' : 'bad' ?>"><?= $r['answered'] ? 'respondido' : 'sem resposta' ?></span><?php endif; ?></p>
<p style="white-space:pre-wrap;margin:6px 0 10px"><?= e($r['body']) ?></p>
<form method="post" style="display:flex;gap:8px;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<input type="text" name="body" placeholder="Responder..." style="flex:1;min-width:220px"><button class="btn btn-sm" name="action" value="reply">Responder</button>
<button class="btn btn-danger btn-sm" name="action" value="delete" data-confirm-click="Remover comentário?">Remover</button></form></div>
<?php endforeach; if (!$rows): ?><p class="hint">Nenhum comentário ainda.</p><?php endif; ?>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
