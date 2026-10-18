<?php
// Notificações do Mercado Pago. Sem sessão/CSRF; a segurança vem da consulta direta à API (+ assinatura opcional).
require __DIR__ . '/app/bootstrap.php';
$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true) ?: [];
$type = $_GET['type'] ?? $_GET['topic'] ?? ($body['type'] ?? '');
$dataId = (string)($_GET['data_id'] ?? $_GET['data']['id'] ?? $_GET['id'] ?? ($body['data']['id'] ?? ''));

if ($type !== 'payment' || $dataId === '') { http_response_code(200); exit('ignored'); }
if (!webhook_signature_ok($dataId)) { http_response_code(401); exit('bad signature'); }
try {
    process_payment($dataId);
    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    error_log('Webhook MP: ' . $e->getMessage());
    http_response_code(500); // MP reenviará
    echo 'error';
}
