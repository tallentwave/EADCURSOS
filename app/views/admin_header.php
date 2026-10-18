<?php $cur = basename($_SERVER['SCRIPT_NAME'], '.php'); $menu = [
  'index' => ['Painel', 'index.php'], 'cursos' => ['Cursos', 'cursos.php'], 'alunos' => ['Alunos', 'alunos.php'],
  'pedidos' => ['Pedidos', 'pedidos.php'], 'certificados' => ['Certificados', 'certificados.php'], 'cupons' => ['Cupons', 'cupons.php'], 'vouchers' => ['Vouchers', 'vouchers.php'], 'comentarios' => ['Comentários', 'comentarios.php'], 'config' => ['Configurações', 'config.php'],
]; ?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title>Admin · <?= e(site_name()) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body><div class="admin">
<aside><a class="brand" href="<?= e(url('admin/')) ?>"><?= brand_html() ?></a>
<?php foreach ($menu as $k => [$label, $file]): ?><a class="m <?= ($cur === $k || ($k === 'cursos' && in_array($cur, ['curso_edit', 'conteudo', 'aula_edit', 'prova'], true))) ? 'on' : '' ?>" href="<?= e(url('admin/' . $file)) ?>"><?= e($label) ?></a><?php endforeach; ?>
<hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
<a class="m" href="<?= e(url('home.php')) ?>">Ver plataforma</a>
<form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" style="margin:8px 14px">Sair</button></form>
</aside><div class="content"><?= flashes() ?>
