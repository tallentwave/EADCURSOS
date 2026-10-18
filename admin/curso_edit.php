<?php
require __DIR__ . '/_init.php';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$c = $id ? course_by_id($id) : null;
if ($id && !$c) back('cursos.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $t = trim($_POST['title'] ?? '');
        if ($t === '') throw new RuntimeException('Informe o título.');
        $price = max(0, (float)str_replace(',', '.', preg_replace('/[^\d,.]/', '', $_POST['price'] ?? '0')));
        $cover = save_image($_FILES['cover'] ?? [], 'cover') ?? ($c['cover'] ?? null);
        $banner = save_image($_FILES['banner'] ?? [], 'banner') ?? ($c['banner'] ?? null);
        $data = [$t, trim($_POST['category'] ?? '') ?: 'Geral', mb_substr(trim($_POST['short_desc'] ?? ''), 0, 255), trim($_POST['description'] ?? ''),
                 $price, $cover, $banner, isset($_POST['featured']) ? 1 : 0, ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft'];
        if (isset($_POST['featured'])) q('UPDATE courses SET featured = 0');
        if ($c) {
            q('UPDATE courses SET title=?, category=?, short_desc=?, description=?, price=?, cover=?, banner=?, featured=?, status=? WHERE id=?', [...$data, $id]);
        } else {
            $slug = slugify($t); $n = 1;
            while (q_val('SELECT 1 FROM courses WHERE slug = ?', [$slug])) $slug = slugify($t) . '-' . (++$n);
            q('INSERT INTO courses (title, category, short_desc, description, price, cover, banner, featured, status, slug, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [...$data, $slug, now()]);
            $id = (int)db()->lastInsertId();
        }
        flash('ok', 'Curso salvo.');
        back('conteudo.php?curso=' . $id);
    } catch (RuntimeException $e) {
        flash('err', $e->getMessage());
        $c = array_merge($c ?? [], $_POST);
    }
}
$c = $c ?? ['title' => '', 'category' => 'Geral', 'short_desc' => '', 'description' => '', 'price' => '0.00', 'featured' => 0, 'status' => 'draft', 'cover' => null, 'banner' => null];
require ROOT . '/app/views/admin_header.php';
?>
<h1><?= $id ? 'Editar curso' : 'Novo curso' ?></h1>
<form class="formbox" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<div class="grid2"><div><label>Título</label><input type="text" name="title" required maxlength="190" value="<?= e($c['title']) ?>"></div>
<div><label>Categoria (agrupa as linhas na vitrine)</label><input type="text" name="category" maxlength="80" value="<?= e($c['category']) ?>"></div></div>
<label>Resumo (aparece nos cards)</label><input type="text" name="short_desc" maxlength="255" value="<?= e($c['short_desc']) ?>">
<label>Descrição completa</label><textarea name="description"><?= e($c['description']) ?></textarea>
<div class="grid2"><div><label>Preço (R$) — 0 para curso gratuito</label><input type="text" name="price" value="<?= e(number_format((float)$c['price'], 2, ',', '')) ?>"></div>
<div><label>Status</label><select name="status"><option value="draft" <?= $c['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option><option value="published" <?= $c['status'] === 'published' ? 'selected' : '' ?>>Publicado</option></select></div></div>
<div class="grid2"><div><label>Capa (16:9, JPG/PNG/WEBP até 5MB)</label><input type="file" name="cover" accept="image/jpeg,image/png,image/webp"><?php if ($c['cover']): ?><img src="<?= e(img_url($c['cover'])) ?>" style="width:160px;margin-top:8px;border-radius:6px"><?php endif; ?></div>
<div><label>Banner de destaque (opcional, formato largo)</label><input type="file" name="banner" accept="image/jpeg,image/png,image/webp"><?php if ($c['banner']): ?><img src="<?= e(img_url($c['banner'])) ?>" style="width:160px;margin-top:8px;border-radius:6px"><?php endif; ?></div></div>
<label class="check"><input type="checkbox" name="featured" <?= $c['featured'] ? 'checked' : '' ?>> Destacar este curso no topo da vitrine</label>
<br><button class="btn">Salvar</button> <a class="btn btn-ghost" href="<?= e(url('admin/cursos.php')) ?>">Cancelar</a>
</form>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
