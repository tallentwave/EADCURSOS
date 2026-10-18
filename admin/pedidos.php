<?php
require __DIR__ . '/_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $o = q_one('SELECT * FROM orders WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    if ($o && ($_POST['action'] ?? '') === 'approve' && $o['status'] !== 'paid') {
        q('UPDATE orders SET status="paid", paid_at=? WHERE id=?', [now(), $o['id']]);
        grant_access((int)$o['user_id'], (int)$o['course_id'], 'purchase', (int)$o['id']);
        mail_purchase((int)$o['id']);
        flash('ok', 'Pedido aprovado manualmente e acesso liberado.');
    } elseif ($o && ($_POST['action'] ?? '') === 'refund' && $o['status'] === 'paid') {
        q('UPDATE orders SET status="refunded" WHERE id=?', [$o['id']]);
        q('DELETE FROM enrollments WHERE order_id = ?', [$o['id']]);
        flash('ok', 'Pedido marcado como reembolsado e acesso removido (faça o estorno no Mercado Pago).');
    }
    back('pedidos.php');
}
$rows = q_all('SELECT o.*, u.name, u.email, c.title FROM orders o JOIN users u ON u.id = o.user_id JOIN courses c ON c.id = o.course_id ORDER BY o.id DESC LIMIT 300');
$st = ['pending' => ['Pendente', 'warn'], 'paid' => ['Pago', 'ok'], 'cancelled' => ['Cancelado', 'bad'], 'refunded' => ['Reembolsado', 'bad']];
require ROOT . '/app/views/admin_header.php';
?>
<h1>Pedidos</h1>
<div class="table-wrap"><table><tr><th>#</th><th>Aluno</th><th>Curso</th><th>Valor</th><th>Status</th><th>Criado</th><th>Pagamento MP</th><th></th></tr>
<?php foreach ($rows as $o): ?><tr><td><?= (int)$o['id'] ?></td><td><?= e($o['name']) ?><br><small style="color:var(--muted)"><?= e($o['email']) ?></small></td><td><?= e($o['title']) ?></td><td><?= money($o['amount']) ?></td>
<td><span class="tag-s tag-<?= $st[$o['status']][1] ?>"><?= $st[$o['status']][0] ?></span></td><td><?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></td><td><?= e($o['mp_payment_id'] ?? '—') ?></td>
<td><form class="inline" method="post" data-confirm="Confirmar ação?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
<?php if ($o['status'] !== 'paid'): ?><button class="btn btn-sm" name="action" value="approve">Aprovar manual</button><?php else: ?><button class="btn btn-danger btn-sm" name="action" value="refund">Reembolsar</button><?php endif; ?></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8">Sem pedidos.</td></tr><?php endif; ?></table></div>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
