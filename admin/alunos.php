<?php
require __DIR__ . '/_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    $uid = (int)($_POST['user_id'] ?? 0);
    $target = q_one('SELECT * FROM users WHERE id = ?', [$uid]);
    if ($a === 'create') {
        $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $pass = (string)($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('err', 'Nome ou e-mail inválido.');
        elseif ($err = password_ok($pass)) flash('err', $err);
        elseif (q_val('SELECT 1 FROM users WHERE email = ?', [$email])) flash('err', 'E-mail já cadastrado.');
        else {
            q('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?,?,?,"student",?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT), now()]);
            $nid = (int)db()->lastInsertId();
            foreach ((array)($_POST['courses'] ?? []) as $cid) if (course_by_id((int)$cid)) grant_access($nid, (int)$cid, 'admin');
            flash('ok', 'Aluno criado.');
        }
    } elseif ($target && $a === 'grant' && course_by_id((int)$_POST['course_id'])) {
        $exp = trim($_POST['expires'] ?? '');
        grant_access($uid, (int)$_POST['course_id'], 'admin');
        if ($exp !== '' && strtotime($exp)) q('UPDATE enrollments SET expires_at = ? WHERE user_id = ? AND course_id = ?', [date('Y-m-d 23:59:59', strtotime($exp)), $uid, $_POST['course_id']]);
        flash('ok', 'Curso liberado.');
    } elseif ($target && $a === 'revoke') {
        q('DELETE FROM enrollments WHERE user_id = ? AND course_id = ?', [$uid, (int)$_POST['course_id']]);
        flash('ok', 'Acesso removido.');
    } elseif ($target && $a === 'block' && $target['role'] !== 'admin') {
        q('UPDATE users SET status = IF(status="active","blocked","active") WHERE id = ?', [$uid]);
    } elseif ($target && $a === 'reset') {
        $p = (string)($_POST['password'] ?? '');
        if ($err = password_ok($p)) flash('err', $err);
        else { q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($p, PASSWORD_DEFAULT), $uid]); flash('ok', 'Senha redefinida.'); }
    } elseif ($target && $a === 'delete' && $target['role'] !== 'admin') {
        q('DELETE FROM users WHERE id = ?', [$uid]); flash('ok', 'Aluno excluído.');
    }
    back('alunos.php' . (isset($_POST['stay']) ? '?id=' . $uid : ''));
}
$courses = q_all('SELECT id, title FROM courses ORDER BY title');
$sel = isset($_GET['id']) ? q_one('SELECT * FROM users WHERE id = ?', [(int)$_GET['id']]) : null;
$qs = trim($_GET['q'] ?? '');
require ROOT . '/app/views/admin_header.php';
if ($sel):
    $en = q_all('SELECT e.*, c.title FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.user_id = ?', [$sel['id']]);
    $enIds = array_column($en, 'course_id');
?>
<h1><?= e($sel['name']) ?></h1><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/alunos.php')) ?>">&laquo; Alunos</a><br><br>
<p><?= e($sel['email']) ?> · <?= $sel['status'] === 'active' ? 'Ativo' : 'Bloqueado' ?> · Cadastro <?= e(date('d/m/Y', strtotime($sel['created_at']))) ?></p><br>
<div class="grid2">
<div class="formbox"><h2 style="margin:14px 0 6px">Cursos liberados</h2>
<?php foreach ($en as $e): ?><div class="mat"><span style="flex:1"><?= e($e['title']) ?> <small style="color:var(--muted)">· <?= $e['source'] === 'purchase' ? 'compra' : 'manual' ?><?= $e['expires_at'] ? ' · expira ' . e(date('d/m/Y', strtotime($e['expires_at']))) : '' ?></small></span>
<form class="inline" method="post" data-confirm="Remover o acesso?"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$sel['id'] ?>"><input type="hidden" name="stay" value="1"><input type="hidden" name="action" value="revoke"><input type="hidden" name="course_id" value="<?= (int)$e['course_id'] ?>"><button class="btn btn-danger btn-sm">Remover</button></form></div>
<?php endforeach; if (!$en): ?><p class="hint">Nenhum curso.</p><?php endif; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$sel['id'] ?>"><input type="hidden" name="stay" value="1"><input type="hidden" name="action" value="grant">
<label>Liberar curso</label><select name="course_id"><?php foreach ($courses as $c) if (!in_array($c['id'], $enIds)) echo '<option value="' . (int)$c['id'] . '">' . e($c['title']) . '</option>'; ?></select>
<label>Expira em (opcional)</label><input type="text" name="expires" placeholder="AAAA-MM-DD"><br><button class="btn btn-sm">Liberar</button></form></div>
<div class="formbox"><h2 style="margin:14px 0 6px">Ações</h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$sel['id'] ?>"><input type="hidden" name="stay" value="1"><input type="hidden" name="action" value="reset">
<label>Definir nova senha</label><input type="password" name="password" minlength="8" required><br><button class="btn btn-sm">Redefinir senha</button></form><br>
<?php if ($sel['role'] !== 'admin'): ?>
<form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$sel['id'] ?>"><input type="hidden" name="stay" value="1"><input type="hidden" name="action" value="block"><button class="btn btn-ghost btn-sm"><?= $sel['status'] === 'active' ? 'Bloquear acesso' : 'Desbloquear' ?></button></form>
<form class="inline" method="post" data-confirm="Excluir aluno definitivamente?"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$sel['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm">Excluir aluno</button></form>
<?php endif; ?></div></div>
<?php else:
    $rows = $qs !== '' ? q_all('SELECT u.*, (SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id) AS n FROM users u WHERE u.name LIKE ? OR u.email LIKE ? ORDER BY u.id DESC LIMIT 200', ["%$qs%", "%$qs%"])
                    : q_all('SELECT u.*, (SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id) AS n FROM users u ORDER BY u.id DESC LIMIT 200'); ?>
<h1>Alunos</h1>
<form class="toolbar" method="get"><input type="text" name="q" value="<?= e($qs) ?>" placeholder="Buscar por nome ou e-mail" style="max-width:320px"><button class="btn btn-sm">Buscar</button></form>
<div class="table-wrap"><table><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Cursos</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td><?= $r['role'] === 'admin' ? 'Admin' : 'Aluno' ?></td><td><?= (int)$r['n'] ?></td>
<td><span class="tag-s tag-<?= $r['status'] === 'active' ? 'ok' : 'bad' ?>"><?= $r['status'] === 'active' ? 'Ativo' : 'Bloqueado' ?></span></td>
<td><a class="btn btn-blue btn-sm" href="<?= e(url('admin/alunos.php?id=' . $r['id'])) ?>">Gerenciar</a></td></tr><?php endforeach; ?></table></div>
<form class="formbox" method="post" style="margin-top:24px"><?= csrf_field() ?><input type="hidden" name="action" value="create"><h2 style="margin:14px 0 0">Cadastrar aluno manualmente</h2>
<div class="grid2"><div><label>Nome</label><input type="text" name="name" required></div><div><label>E-mail</label><input type="email" name="email" required></div></div>
<label>Senha inicial</label><input type="password" name="password" required minlength="8">
<label>Liberar cursos</label><?php foreach ($courses as $c): ?><label class="check"><input type="checkbox" name="courses[]" value="<?= (int)$c['id'] ?>"> <?= e($c['title']) ?></label><?php endforeach; ?>
<br><button class="btn">Criar aluno</button></form>
<?php endif; require ROOT . '/app/views/admin_footer.php'; ?>
