<?php
declare(strict_types=1);

function coupon_normalize(string $code): string { return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $code)); }

/**
 * Valida um cupom para um curso/usuário.
 * @return array{0:?array,1:float,2:float,3:string} [cupom, valor final, desconto, erro]
 */
function coupon_apply(string $code, array $course, int $uid): array
{
    $price = round((float)$course['price'], 2);
    $code = coupon_normalize($code);
    if ($code === '') return [null, $price, 0.0, ''];
    $c = q_one('SELECT * FROM coupons WHERE code = ?', [$code]);
    $err = '';
    if (!$c || !$c['active']) $err = 'Cupom inválido.';
    elseif ($c['expires_at'] && strtotime($c['expires_at']) < time()) $err = 'Cupom expirado.';
    elseif ($c['course_id'] && (int)$c['course_id'] !== (int)$course['id']) $err = 'Este cupom não vale para este curso.';
    elseif ($c['max_uses'] !== null && coupon_uses((int)$c['id']) >= (int)$c['max_uses']) $err = 'Este cupom atingiu o limite de usos.';
    elseif (q_val('SELECT 1 FROM orders WHERE coupon_id = ? AND user_id = ? AND status = "paid"', [$c['id'], $uid])) $err = 'Você já utilizou este cupom.';
    if ($err !== '') return [null, $price, 0.0, $err];

    $disc = $c['type'] === 'percent' ? $price * min(100.0, (float)$c['value']) / 100 : (float)$c['value'];
    $disc = round(min($price, max(0, $disc)), 2);
    return [$c, round($price - $disc, 2), $disc, ''];
}

function coupon_uses(int $id): int { return (int)q_val('SELECT COUNT(*) FROM orders WHERE coupon_id = ? AND status = "paid"', [$id]); }

/** Pedido já aprovado (cupom de 100% ou voucher): registra e libera na hora. */
function complete_free_order(array $user, array $course, ?int $couponId, float $discount): int
{
    q('INSERT INTO orders (user_id, course_id, amount, status, coupon_id, discount, created_at, paid_at) VALUES (?, ?, 0, "paid", ?, ?, ?, ?)',
        [$user['id'], $course['id'], $couponId, $discount, now(), now()]);
    $oid = (int)db()->lastInsertId();
    grant_access((int)$user['id'], (int)$course['id'], 'purchase', $oid);
    mail_purchase($oid);
    return $oid;
}

// ---------- Vouchers ----------
function voucher_generate_code(): string
{
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem 0/O/1/I
    $parts = [];
    for ($i = 0; $i < 3; $i++) {
        $p = '';
        for ($j = 0; $j < 4; $j++) $p .= $alpha[random_int(0, strlen($alpha) - 1)];
        $parts[] = $p;
    }
    return implode('-', $parts);
}
function voucher_normalize(string $c): string
{
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c));
    return strlen($c) === 12 ? implode('-', str_split($c, 4)) : '';
}

/** @return array{0:bool,1:string} */
function voucher_redeem(int $uid, string $input): array
{
    $key = 'vch:' . $uid;
    if (too_many_attempts($key)) return [false, 'Muitas tentativas. Aguarde 15 minutos.'];
    $code = voucher_normalize($input);
    $v = $code !== '' ? q_one('SELECT * FROM vouchers WHERE code = ?', [$code]) : null;
    if (!$v || $v['redeemed_by'] !== null || ($v['expires_at'] && strtotime($v['expires_at']) < time())) {
        record_attempt($key);
        return [false, 'Voucher inválido, expirado ou já utilizado.'];
    }
    if (has_access($uid, (int)$v['course_id'])) return [false, 'Você já tem acesso a este curso. O voucher não foi consumido.'];
    // resgate atômico: só um usuário consegue marcar o voucher
    $n = q('UPDATE vouchers SET redeemed_by = ?, redeemed_at = ? WHERE id = ? AND redeemed_by IS NULL', [$uid, now(), $v['id']])->rowCount();
    if ($n !== 1) { record_attempt($key); return [false, 'Voucher inválido, expirado ou já utilizado.']; }
    grant_access($uid, (int)$v['course_id'], 'voucher');
    return [true, (string)q_val('SELECT title FROM courses WHERE id = ?', [$v['course_id']])];
}
