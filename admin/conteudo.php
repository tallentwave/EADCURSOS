<?php
require __DIR__ . '/_init.php';
$cid = (int)($_GET['curso'] ?? $_POST['curso'] ?? 0);
$c = course_by_id($cid);
if (!$c) back('cursos.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    $mid = (int)($_POST['module_id'] ?? 0);
    $owns = fn(int $m) => (bool)q_val('SELECT 1 FROM modules WHERE id = ? AND course_id = ?', [$m, $cid]);
    if ($a === 'add_module' && trim($_POST['title'] ?? '') !== '') {
        $pos = (int)q_val('SELECT COALESCE(MAX(position),0)+1 FROM modules WHERE course_id = ?', [$cid]);
        q('INSERT INTO modules (course_id, title, position) VALUES (?,?,?)', [$cid, trim($_POST['title']), $pos]);
    } elseif ($a === 'edit_module' && $owns($mid)) {
        q('UPDATE modules SET title = ?, position = ? WHERE id = ?', [trim($_POST['title']) ?: 'Módulo', (int)$_POST['position'], $mid]);
    } elseif ($a === 'del_module' && $owns($mid)) {
        q('DELETE FROM modules WHERE id = ?', [$mid]);
    } elseif ($a === 'add_lesson' && $owns($mid) && trim($_POST['title'] ?? '') !== '') {
        $pos = (int)q_val('SELECT COALESCE(MAX(position),0)+1 FROM lessons WHERE module_id = ?', [$mid]);
        $v = trim($_POST['video_url'] ?? '');
        if ($v !== '' && !video_embed($v)) flash('err', 'Link de vídeo inválido: use YouTube ou Vimeo.');
        else q('INSERT INTO lessons (module_id, title, video_url, duration_min, position, is_free) VALUES (?,?,?,?,?,?)',
            [$mid, trim($_POST['title']), $v, max(0, (int)($_POST['duration_min'] ?? 0)), $pos, isset($_POST['is_free']) ? 1 : 0]);
    } elseif ($a === 'del_lesson') {
        q('DELETE l FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = ? AND m.course_id = ?', [(int)$_POST['lesson_id'], $cid]);
    }
    back('conteudo.php?curso=' . $cid);
}
$mods = q_all('SELECT * FROM modules WHERE course_id = ? ORDER BY position, id', [$cid]);
$by = [];
foreach (course_lessons($cid) as $l) $by[$l['module_id']][] = $l;
require ROOT . '/app/views/admin_header.php';
?>
<h1>Conteúdo: <?= e($c['title']) ?></h1>
<div class="toolbar"><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/cursos.php')) ?>">&laquo; Cursos</a>
<a class="btn btn-ghost btn-sm" href="<?= e(url('admin/curso_edit.php?id=' . $cid)) ?>">Editar dados do curso</a></div>

<form class="formbox" method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><?= csrf_field() ?>
<input type="hidden" name="curso" value="<?= $cid ?>"><input type="hidden" name="action" value="add_module">
<div style="flex:1;min-width:240px"><label>Novo módulo</label><input type="text" name="title" required placeholder="Ex.: Módulo 1 — Introdução"></div><button class="btn">Adicionar módulo</button></form>

<?php foreach ($mods as $m): ?>
<div class="formbox">
  <form method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><?= csrf_field() ?>
    <input type="hidden" name="curso" value="<?= $cid ?>"><input type="hidden" name="module_id" value="<?= (int)$m['id'] ?>">
    <div style="flex:1;min-width:220px"><label>Módulo</label><input type="text" name="title" value="<?= e($m['title']) ?>"></div>
    <div style="width:90px"><label>Ordem</label><input type="number" name="position" value="<?= (int)$m['position'] ?>"></div>
    <button class="btn btn-sm" name="action" value="edit_module">Salvar</button>
    <button class="btn btn-danger btn-sm" name="action" value="del_module" onclick="return confirm('Excluir módulo e suas aulas?')">Excluir</button>
  </form>
  <table style="margin-top:14px"><tr><th>Aula</th><th>Vídeo</th><th>Min</th><th>Grátis</th><th></th></tr>
  <?php foreach ($by[$m['id']] ?? [] as $l): ?><tr>
    <td><?= e($l['title']) ?></td><td><?= video_embed($l['video_url']) ? '&#10003;' : '<span style="color:var(--danger)">sem vídeo</span>' ?></td>
    <td><?= (int)$l['duration_min'] ?></td><td><?= $l['is_free'] ? 'Sim' : '—' ?></td>
    <td style="white-space:nowrap"><a class="btn btn-blue btn-sm" href="<?= e(url('admin/aula_edit.php?id=' . $l['id'])) ?>">Editar / materiais</a>
    <form class="inline" method="post" data-confirm="Excluir esta aula?"><?= csrf_field() ?><input type="hidden" name="curso" value="<?= $cid ?>"><input type="hidden" name="action" value="del_lesson"><input type="hidden" name="lesson_id" value="<?= (int)$l['id'] ?>"><button class="btn btn-danger btn-sm">Excluir</button></form></td></tr>
  <?php endforeach; ?></table>
  <form method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:8px"><?= csrf_field() ?>
    <input type="hidden" name="curso" value="<?= $cid ?>"><input type="hidden" name="module_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="add_lesson">
    <div style="flex:2;min-width:200px"><label>Nova aula</label><input type="text" name="title" required placeholder="Título da aula"></div>
    <div style="flex:2;min-width:220px"><label>Link YouTube/Vimeo</label><input type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=..."></div>
    <div style="width:90px"><label>Minutos</label><input type="number" name="duration_min" min="0" value="0"></div>
    <label class="check" style="margin-bottom:10px"><input type="checkbox" name="is_free"> Aula grátis</label>
    <button class="btn btn-sm">+ Aula</button></form>
</div>
<?php endforeach; if (!$mods): ?><p class="hint">Crie o primeiro módulo acima.</p><?php endif; ?>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
