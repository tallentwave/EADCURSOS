<?php
require __DIR__ . '/app/bootstrap.php';
$u = current_user();
if (!$u) { http_response_code(401); exit; }
$cid = (int)($_GET['curso'] ?? 0);
$face = ($_GET['face'] ?? 'front') === 'back' ? 'back' : 'front';
$cert = card_cert((int)$u['id'], $cid);
if (!$cert || !has_access((int)$u['id'], $cid)) { http_response_code(404); exit; }
$photo = user_photo_bytes(q_val('SELECT photo FROM users WHERE id = ?', [$u['id']]));
$png = card_png(card_data($cert, $photo), $face);
header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
header('Cache-Control: private, no-store');
if (!empty($_GET['dl'])) header('Content-Disposition: attachment; filename="carteirinha-' . ($face === 'back' ? 'verso' : 'frente') . '.png"');
echo $png;
