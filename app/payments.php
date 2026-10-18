<?php
declare(strict_types=1);

define('MP_API', getenv('MP_API_BASE') ?: 'https://api.mercadopago.com');

function mp_request(string $method, string $path, ?array $body = null): array
{
    $token = setting('mp_access_token');
    if ($token === '') throw new RuntimeException('Mercado Pago não configurado.');
    $ch = curl_init(MP_API . $path);
    $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
    if ($method === 'POST') $headers[] = 'X-Idempotency-Key: ' . bin2hex(random_bytes(16));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) throw new RuntimeException('Falha de conexão com Mercado Pago: ' . $err);
    $json = json_decode($res, true);
    if ($code >= 400 || !is_array($json)) throw new RuntimeException('Mercado Pago retornou erro HTTP ' . $code);
    return $json;
}

/** Cria o pedido e devolve a URL do checkout do Mercado Pago (PIX, cartão, boleto). */
function create_checkout(array $user, array $course): string
{
    q('INSERT INTO orders (user_id, course_id, amount, status, created_at) VALUES (?, ?, ?, "pending", ?)',
        [$user['id'], $course['id'], $course['price'], now()]);
    $orderId = (int)db()->lastInsertId();
    $back = url('retorno.php?pedido=' . $orderId);

    $body = [
        'items' => [[
            'id' => (string)$course['id'],
            'title' => $course['title'],
            'quantity' => 1,
            'currency_id' => 'BRL',
            'unit_price' => (float)$course['price'],
        ]],
        'payer' => ['name' => $user['name'], 'email' => $user['email']],
        'external_reference' => (string)$orderId,
        'notification_url' => url('webhook.php'),
        'back_urls' => ['success' => $back, 'pending' => $back, 'failure' => $back],
        'statement_descriptor' => mb_substr(preg_replace('/[^A-Za-z0-9 ]/', '', site_name()), 0, 22),
    ];
    if (str_starts_with(BASE_URL, 'https://')) $body['auto_return'] = 'approved'; // MP exige https

    try {
        $pref = mp_request('POST', '/checkout/preferences', $body);
        $link = setting('mp_sandbox') === '1' ? ($pref['sandbox_init_point'] ?? '') : ($pref['init_point'] ?? '');
        if ($link === '') throw new RuntimeException('Checkout indisponível.');
    } catch (Throwable $e) {
        q('DELETE FROM orders WHERE id = ?', [$orderId]);
        throw $e;
    }
    q('UPDATE orders SET mp_preference_id = ? WHERE id = ?', [$pref['id'] ?? null, $orderId]);
    return $link;
}

/**
 * Consulta o pagamento na API (fonte confiável) e atualiza pedido/matrícula.
 * Idempotente: pode ser chamada pelo webhook e pela página de retorno.
 */
function process_payment(string $paymentId): ?array
{
    if (!preg_match('/^\d{3,20}$/', $paymentId)) return null;
    $pay = mp_request('GET', '/v1/payments/' . $paymentId);
    $orderId = (int)($pay['external_reference'] ?? 0);
    $order = $orderId ? q_one('SELECT * FROM orders WHERE id = ?', [$orderId]) : null;
    if (!$order) return null;

    $status = $pay['status'] ?? '';
    if ($status === 'approved') {
        if ((float)($pay['transaction_amount'] ?? 0) + 0.001 < (float)$order['amount'] || ($pay['currency_id'] ?? 'BRL') !== 'BRL') {
            return $order; // valor divergente: não libera
        }
        if ($order['status'] !== 'paid') {
            q('UPDATE orders SET status = "paid", mp_payment_id = ?, paid_at = ? WHERE id = ?', [$paymentId, now(), $orderId]);
            grant_access((int)$order['user_id'], (int)$order['course_id'], 'purchase', $orderId);
        }
    } elseif (in_array($status, ['refunded', 'charged_back', 'cancelled'], true)) {
        if ($order['status'] === 'paid' && $status !== 'cancelled') {
            q('UPDATE orders SET status = "refunded" WHERE id = ?', [$orderId]);
            q('DELETE FROM enrollments WHERE order_id = ?', [$orderId]);
        } elseif ($order['status'] === 'pending') {
            q('UPDATE orders SET status = "cancelled" WHERE id = ?', [$orderId]);
        }
    }
    return q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
}

/** Valida x-signature do webhook quando o segredo está configurado. */
function webhook_signature_ok(string $dataId): bool
{
    $secret = setting('mp_webhook_secret');
    if ($secret === '') return true; // validação extra opcional; a confirmação real é feita via API
    $sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $reqId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    $ts = $v1 = '';
    foreach (explode(',', $sig) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 'ts') $ts = $v;
        if ($k === 'v1') $v1 = $v;
    }
    if ($ts === '' || $v1 === '') return false;
    $manifest = 'id:' . strtolower($dataId) . ';request-id:' . $reqId . ';ts:' . $ts . ';';
    return hash_equals(hash_hmac('sha256', $manifest, $secret), $v1);
}
