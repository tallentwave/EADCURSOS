<?php
require __DIR__ . '/_init.php';
$s = [
  'Alunos' => (int)q_val('SELECT COUNT(*) FROM users WHERE role = "student"'),
  'Cursos publicados' => (int)q_val('SELECT COUNT(*) FROM courses WHERE status = "published"'),
  'Matrículas ativas' => (int)q_val('SELECT COUNT(*) FROM enrollments'),
  'Vendas aprovadas' => (int)q_val('SELECT COUNT(*) FROM orders WHERE status = "paid"'),
  'Receita total' => money(q_val('SELECT COALESCE(SUM(amount),0) FROM orders WHERE status = "paid"')),
  'Receita (30 dias)' => money(q_val('SELECT COALESCE(SUM(amount),0) FROM orders WHERE status = "paid" AND paid_at > ?', [date('Y-m-d', strtotime('-30 days'))])),
];
$recent = q_all('SELECT o.*, u.name, c.title FROM orders o JOIN users u ON u.id = o.user_id JOIN courses c ON c.id = o.course_id ORDER BY o.id DESC LIMIT 8');
$top = q_all('SELECT c.title, COUNT(e.id) AS n FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id GROUP BY c.id ORDER BY n DESC LIMIT 5');
require ROOT . '/app/views/admin_header.php';
$st = ['pending' => ['Pendente', 'warn'], 'paid' => ['Pago', 'ok'], 'cancelled' => ['Cancelado', 'bad'], 'refunded' => ['Reembolsado', 'bad']];
?>
<h1>Painel</h1>
<div class="stats"><?php foreach ($s as $k => $v): ?><div class="stat"><b><?= e($v) ?></b><span><?= e($k) ?></span></div><?php endforeach; ?></div>
<div class="grid2">
<div><h2 style="margin-bottom:10px">Últimos pedidos</h2><div class="table-wrap"><table><tr><th>Aluno</th><th>Curso</th><th>Valor</th><th>Status</th></tr>
<?php foreach ($recent as $o): ?><tr><td><?= e($o['name']) ?></td><td><?= e($o['title']) ?></td><td><?= money($o['amount']) ?></td><td><span class="tag-s tag-<?= $st[$o['status']][1] ?>"><?= $st[$o['status']][0] ?></span></td></tr>
<?php endforeach; if (!$recent): ?><tr><td colspan="4">Sem pedidos.</td></tr><?php endif; ?></table></div></div>
<div><h2 style="margin-bottom:10px">Cursos mais matriculados</h2><div class="table-wrap"><table><tr><th>Curso</th><th>Matrículas</th></tr>
<?php foreach ($top as $t): ?><tr><td><?= e($t['title']) ?></td><td><?= (int)$t['n'] ?></td></tr><?php endforeach; ?></table></div></div>
</div>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
