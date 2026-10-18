<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
require_post();
$c = course_by_id((int)($_POST['course_id'] ?? 0));
if (!$c || $c['status'] !== 'published') { http_response_code(404); exit('Curso não encontrado.'); }
if (has_access((int)$u['id'], (int)$c['id'])) redirect('curso.php?id=' . $c['id']);
$back = 'curso.php?id=' . $c['id'];

if ((float)$c['price'] <= 0) {
    grant_access((int)$u['id'], (int)$c['id'], 'admin');
    flash('ok', 'Acesso liberado!');
    redirect($back);
}
[$coupon, $final, $discount, $err] = coupon_apply((string)($_POST['coupon'] ?? ''), $c, (int)$u['id']);
if ($err !== '') { flash('err', $err); redirect($back); }

if ($final <= 0) { // cupom de 100%
    complete_free_order($u, $c, $coupon ? (int)$coupon['id'] : null, $discount);
    flash('ok', 'Cupom aplicado! Acesso liberado.');
    redirect($back);
}
if ($final < 1) { flash('err', 'O valor mínimo para pagamento é R$ 1,00. Ajuste o cupom.'); redirect($back); }
try {
    redirect(create_checkout($u, $c, $final, $coupon ? (int)$coupon['id'] : null, $discount));
} catch (Throwable $e) {
    error_log('Checkout: ' . $e->getMessage());
    flash('err', 'Não foi possível iniciar o pagamento agora. Tente novamente em instantes.');
    redirect($back);
}
