<?php
require __DIR__ . '/_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') { q('DELETE FROM courses WHERE id = ?', [$id]); flash('ok', 'Curso excluído.'); }
    if (($_POST['action'] ?? '') === 'toggle') { q('UPDATE courses SET status = IF(status="published","draft","published") WHERE id = ?', [$id]); }
    back('cursos.php');
}
$rows = q_all('SELECT c.*, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS alunos,
  (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = c.id) AS aulas FROM courses c ORDER BY c.id DESC');
require ROOT . '/app/views/admin_header.php';
?>
<h1>Cursos</h1>
<div class="toolbar"><a class="btn" href="<?= e(url('admin/curso_edit.php')) ?>">+ Novo curso</a></div>
<div class="table-wrap"><table><tr><th></th><th>Curso</th><th>Preço</th><th>Aulas</th><th>Alunos</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $c): ?><tr>
<td style="width:90px"><img src="<?= e(img_url($c['cover'])) ?>" alt="" style="width:80px;border-radius:6px"></td>
<td><strong><?= e($c['title']) ?></strong><br><small style="color:var(--muted)"><?= e($c['category']) ?></small></td>
<td><?= (float)$c['price'] > 0 ? money($c['price']) : 'Grátis' ?></td><td><?= (int)$c['aulas'] ?></td><td><?= (int)$c['alunos'] ?></td>
<td><form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="toggle">
  <button class="tag-s tag-<?= $c['status'] === 'published' ? 'ok' : 'warn' ?>" style="border:0;cursor:pointer"><?= $c['status'] === 'published' ? 'Publicado' : 'Rascunho' ?></button></form></td>
<td style="white-space:nowrap"><a class="btn btn-blue btn-sm" href="<?= e(url('admin/conteudo.php?curso=' . $c['id'])) ?>">Conteúdo</a>
<a class="btn btn-ghost btn-sm" href="<?= e(url('admin/curso_edit.php?id=' . $c['id'])) ?>">Editar</a>
<form class="inline" method="post" data-confirm="Excluir o curso e todo o seu conteúdo?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm">Excluir</button></form></td>
</tr><?php endforeach; if (!$rows): ?><tr><td colspan="7">Nenhum curso cadastrado.</td></tr><?php endif; ?></table></div>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
