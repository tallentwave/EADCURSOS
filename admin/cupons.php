<?php
require __DIR__ . '/_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($a === 'create') {
        $code = coupon_normalize((string)($_POST['code'] ?? '')) ?: strtoupper(bin2hex(random_bytes(4)));
        $type = ($_POST['type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
        $val = (float)str_replace(',', '.', preg_replace('/[^\d,.]/', '', $_POST['value'] ?? '0'));
        $max = trim($_POST['max_uses'] ?? '') !== '' ? max(1, (int)$_POST['max_uses']) : null;
        $exp = trim($_POST['expires'] ?? '') !== '' && strtotime($_POST['expires']) ? date('Y-m-d 23:59:59', strtotime($_POST['expires'])) : null;
        $cid = (int)($_POST['course_id'] ?? 0) ?: null;
        if ($val <= 0 || ($type === 'percent' && $val > 100)) flash('err', 'Valor do desconto inválido.');
        elseif ($cid && !course_by_id($cid)) flash('err', 'Curso inválido.');
        elseif (q_val('SELECT 1 FROM coupons WHERE code = ?', [$code])) flash('err', 'Já existe um cupom com este código.');
        else {
            q('INSERT INTO coupons (code, type, value, course_id, max_uses, expires_at, created_at) VALUES (?,?,?,?,?,?,?)', [$code, $type, $val, $cid, $max, $exp, now()]);
            flash('ok', "Cupom $code criado.");
        }
    } elseif ($a === 'toggle') q('UPDATE coupons SET active = 1 - active WHERE id = ?', [$id]);
    elseif ($a === 'delete') { q('UPDATE orders SET coupon_id = NULL WHERE coupon_id = ?', [$id]); q('DELETE FROM coupons WHERE id = ?', [$id]); flash('ok', 'Cupom excluído.'); }
    back('cupons.php');
}
$rows = q_all('SELECT c.*, co.title AS course, (SELECT COUNT(*) FROM orders o WHERE o.coupon_id = c.id AND o.status = "paid") AS uses
               FROM coupons c LEFT JOIN courses co ON co.id = c.course_id ORDER BY c.id DESC');
$courses = q_all('SELECT id, title FROM courses ORDER BY title');
require ROOT . '/app/views/admin_header.php';
?>
<h1>Cupons de desconto</h1>
<form class="formbox" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<div class="grid2"><div><label>Código (deixe vazio para gerar)</label><input type="text" name="code" maxlength="40" style="text-transform:uppercase" placeholder="BEMVINDO10"></div>
<div><label>Curso (opcional)</label><select name="course_id"><option value="0">Todos os cursos</option><?php foreach ($courses as $c) echo '<option value="' . (int)$c['id'] . '">' . e($c['title']) . '</option>'; ?></select></div>
<div><label>Tipo</label><select name="type"><option value="percent">Porcentagem (%)</option><option value="fixed">Valor fixo (R$)</option></select></div>
<div><label>Valor do desconto</label><input type="text" name="value" required placeholder="10"></div>
<div><label>Limite de usos (opcional)</label><input type="number" name="max_uses" min="1"></div>
<div><label>Válido até (opcional)</label><input type="text" name="expires" placeholder="AAAA-MM-DD"></div></div>
<br><button class="btn">Criar cupom</button></form>
<div class="table-wrap"><table><tr><th>Código</th><th>Desconto</th><th>Curso</th><th>Usos</th><th>Validade</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $c): $expired = $c['expires_at'] && strtotime($c['expires_at']) < time(); ?><tr>
<td><strong><?= e($c['code']) ?></strong></td><td><?= $c['type'] === 'percent' ? rtrim(rtrim(number_format((float)$c['value'], 2, ',', ''), '0'), ',') . '%' : money($c['value']) ?></td>
<td><?= e($c['course'] ?? 'Todos') ?></td><td><?= (int)$c['uses'] ?><?= $c['max_uses'] !== null ? ' / ' . (int)$c['max_uses'] : '' ?></td>
<td><?= $c['expires_at'] ? e(date('d/m/Y', strtotime($c['expires_at']))) : '—' ?></td>
<td><span class="tag-s tag-<?= $c['active'] && !$expired ? 'ok' : 'bad' ?>"><?= !$c['active'] ? 'Inativo' : ($expired ? 'Expirado' : 'Ativo') ?></span></td>
<td style="white-space:nowrap"><form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="toggle"><?= $c['active'] ? 'Desativar' : 'Ativar' ?></button></form>
<form class="inline" method="post" data-confirm="Excluir o cupom?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-danger btn-sm" name="action" value="delete">Excluir</button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7">Nenhum cupom.</td></tr><?php endif; ?></table></div>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
