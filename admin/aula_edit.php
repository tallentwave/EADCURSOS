<?php
require __DIR__ . '/_init.php';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$l = q_one('SELECT l.*, m.course_id FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = ?', [$id]);
if (!$l) back('cursos.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $v = trim($_POST['video_url'] ?? '');
        if ($v !== '' && !video_embed($v)) flash('err', 'Link de vídeo inválido: use YouTube ou Vimeo.');
        else {
            q('UPDATE lessons SET title=?, description=?, video_url=?, duration_min=?, is_free=? WHERE id=?',
                [trim($_POST['title']) ?: $l['title'], trim($_POST['description'] ?? ''), $v, max(0, (int)$_POST['duration_min']), isset($_POST['is_free']) ? 1 : 0, $id]);
            flash('ok', 'Aula salva.');
        }
    } elseif ($a === 'upload') {
        $f = $_FILES['pdf'] ?? null;
        $title = trim($_POST['mtitle'] ?? '');
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 20 * 1024 * 1024) flash('err', 'Arquivo inválido (máx. 20MB).');
        elseif ((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) !== 'application/pdf' || file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') flash('err', 'Envie apenas arquivos PDF.');
        else {
            $stored = bin2hex(random_bytes(16)) . '.pdf';
            if (move_uploaded_file($f['tmp_name'], ROOT . '/storage/materials/' . $stored)) {
                q('INSERT INTO materials (lesson_id, title, stored_name, size) VALUES (?,?,?,?)', [$id, $title ?: pathinfo($f['name'], PATHINFO_FILENAME), $stored, $f['size']]);
                flash('ok', 'Material enviado.');
            } else flash('err', 'Falha ao salvar o arquivo.');
        }
    } elseif ($a === 'del_material') {
        $m = q_one('SELECT * FROM materials WHERE id = ? AND lesson_id = ?', [(int)$_POST['mid'], $id]);
        if ($m) { @unlink(ROOT . '/storage/materials/' . basename($m['stored_name'])); q('DELETE FROM materials WHERE id = ?', [$m['id']]); }
    }
    back('aula_edit.php?id=' . $id);
}
$mats = q_all('SELECT * FROM materials WHERE lesson_id = ?', [$id]);
require ROOT . '/app/views/admin_header.php';
?>
<h1>Editar aula</h1>
<a class="btn btn-ghost btn-sm" href="<?= e(url('admin/conteudo.php?curso=' . $l['course_id'])) ?>">&laquo; Voltar ao conteúdo</a><br><br>
<form class="formbox" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="save">
<label>Título</label><input type="text" name="title" value="<?= e($l['title']) ?>" required>
<label>Link do vídeo (YouTube ou Vimeo)</label><input type="url" name="video_url" value="<?= e($l['video_url']) ?>">
<label>Descrição</label><textarea name="description"><?= e($l['description']) ?></textarea>
<div class="grid2"><div><label>Duração (min)</label><input type="number" name="duration_min" min="0" value="<?= (int)$l['duration_min'] ?>"></div></div>
<label class="check"><input type="checkbox" name="is_free" <?= $l['is_free'] ? 'checked' : '' ?>> Aula gratuita (visível sem comprar o curso)</label><br><button class="btn">Salvar</button></form>

<div class="formbox"><h2 style="margin:14px 0 6px">Materiais (PDF)</h2>
<?php foreach ($mats as $m): ?><div class="mat">&#128196; <?= e($m['title']) ?> <small style="color:var(--muted)"><?= round($m['size'] / 1024) ?> KB</small>
<form class="inline" method="post" data-confirm="Excluir material?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="del_material"><input type="hidden" name="mid" value="<?= (int)$m['id'] ?>"><button class="btn btn-danger btn-sm">Excluir</button></form></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="upload">
<div class="grid2"><div><label>Nome do material</label><input type="text" name="mtitle" placeholder="Ex.: Apostila da aula"></div><div><label>Arquivo PDF (até 20MB)</label><input type="file" name="pdf" accept="application/pdf" required></div></div><br><button class="btn btn-sm">Enviar PDF</button></form></div>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
