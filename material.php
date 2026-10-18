<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$m = q_one('SELECT mt.*, m.course_id, l.is_free FROM materials mt JOIN lessons l ON l.id = mt.lesson_id JOIN modules m ON m.id = l.module_id WHERE mt.id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$m || (!has_access((int)$u['id'], (int)$m['course_id']) && !$m['is_free'])) { http_response_code(403); exit('Sem acesso.'); }
$path = ROOT . '/storage/materials/' . basename($m['stored_name']);
if (!is_file($path)) { http_response_code(404); exit('Arquivo não encontrado.'); }
$fname = preg_replace('/[^A-Za-z0-9._ -]/', '_', $m['title']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: private, no-store');
readfile($path);
