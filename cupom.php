<?php
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json');
$u = current_user();
if (!$u || $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(403); echo '{"ok":false}'; exit; }
csrf_check();
$c = course_by_id((int)($_POST['course_id'] ?? 0));
if (!$c || $c['status'] !== 'published') { echo '{"ok":false,"msg":"Curso inválido."}'; exit; }
if (too_many_attempts('cpn:' . $u['id'])) { echo json_encode(['ok' => false, 'msg' => 'Muitas tentativas. Aguarde alguns minutos.']); exit; }
[$coupon, $final, $discount, $err] = coupon_apply((string)($_POST['coupon'] ?? ''), $c, (int)$u['id']);
if ($err !== '') { record_attempt('cpn:' . $u['id']); echo json_encode(['ok' => false, 'msg' => $err]); exit; }
echo json_encode(['ok' => true, 'final' => money($final), 'discount' => money($discount), 'msg' => $coupon ? 'Cupom aplicado: -' . money($discount) : '']);
