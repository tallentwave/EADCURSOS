<?php /** @var string $title */ $u = current_user(); ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e(($title ?? '') !== '' ? $title . ' · ' : '') . e(site_name()) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<header class="nav">
  <a class="brand" href="<?= e(url($u ? 'home.php' : 'login.php')) ?>"><span>&#9670;</span> <?= e(site_name()) ?></a>
  <?php if ($u): ?>
  <nav>
    <a href="<?= e(url('home.php')) ?>">Início</a>
    <a href="<?= e(url('home.php#meus-cursos')) ?>">Meus cursos</a>
    <a href="<?= e(url('perfil.php')) ?>">Minha conta</a>
    <?php if ($u['role'] === 'admin'): ?><a class="gold" href="<?= e(url('admin/')) ?>">Admin</a><?php endif; ?>
  </nav>
  <form class="logout" method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Sair</button></form>
  <?php endif; ?>
</header>
<main>
<?= flashes() ?>
