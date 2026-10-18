<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$order = q_one('SELECT o.*, c.title FROM orders o JOIN courses c ON c.id = o.course_id WHERE o.id = ? AND o.user_id = ?', [(int)($_GET['pedido'] ?? 0), $u['id']]);
if (!$order) redirect('home.php');

// concilia na hora (o webhook pode demorar alguns segundos)
$pid = (string)($_GET['payment_id'] ?? $_GET['collection_id'] ?? '');
if ($order['status'] !== 'paid' && $pid !== '') {
    try { process_payment($pid); } catch (Throwable $e) { error_log('Retorno: ' . $e->getMessage()); }
    $order = q_one('SELECT o.*, c.title FROM orders o JOIN courses c ON c.id = o.course_id WHERE o.id = ?', [$order['id']]);
}
$title = 'Pagamento';
require ROOT . '/app/views/header.php';
?>
<div class="container" style="max-width:640px;text-align:center;padding-top:60px">
<?php if ($order['status'] === 'paid'): ?>
  <h1 style="color:var(--gold)">Pagamento aprovado! &#127881;</h1>
  <p style="margin:14px 0 26px">Seu acesso ao curso <strong><?= e($order['title']) ?></strong> já está liberado.</p>
  <a class="btn" href="<?= e(url('curso.php?id=' . $order['course_id'])) ?>">Acessar o curso</a>
<?php elseif ($order['status'] === 'pending'): ?>
  <h1 style="color:var(--gold)">Aguardando confirmação</h1>
  <p style="margin:14px 0 26px">Assim que o pagamento for confirmado (PIX costuma levar segundos; boleto até 2 dias úteis) o curso <strong><?= e($order['title']) ?></strong> será liberado automaticamente.</p>
  <a class="btn btn-ghost" href="<?= e(url('retorno.php?pedido=' . $order['id'])) ?>">Verificar novamente</a>
  <a class="btn" href="<?= e(url('home.php')) ?>">Ir para o início</a>
<?php else: ?>
  <h1 style="color:var(--danger)">Pagamento não concluído</h1>
  <p style="margin:14px 0 26px">O pedido não foi aprovado. Você pode tentar novamente.</p>
  <a class="btn" href="<?= e(url('curso.php?id=' . $order['course_id'])) ?>">Voltar ao curso</a>
<?php endif; ?>
</div>
<?php require ROOT . '/app/views/footer.php'; ?>
