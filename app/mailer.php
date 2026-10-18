<?php
declare(strict_types=1);

/** Último erro de envio (para exibir no teste do admin). */
function mail_last_error(?string $set = null): string
{
    static $e = '';
    if ($set !== null) $e = $set;
    return $e;
}

function mail_clean(string $s): string { return trim(str_replace(["\r", "\n", "\0"], ' ', $s)); }

/** Envia e-mail por SMTP (se configurado) ou mail() como alternativa. */
function mail_send(string $to, string $subject, string $text, ?string $html = null): bool
{
    mail_last_error('');
    $to = mail_clean($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { mail_last_error('Destinatário inválido.'); return false; }

    $fromAddr = mail_clean(setting('mail_from') ?: setting('smtp_user'));
    if (!filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) $fromAddr = 'no-reply@' . preg_replace('/^www\./', '', explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
    $fromName = mail_clean(site_name());
    $html ??= mail_html_wrap($text);

    $b = 'b' . bin2hex(random_bytes(12));
    $headers = [
        'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <$fromAddr>",
        "To: <$to>",
        'Subject: =?UTF-8?B?' . base64_encode(mail_clean($subject)) . '?=',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (explode('@', $fromAddr)[1] ?? 'localhost') . '>',
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"$b\"",
    ];
    $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) .
            "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b--\r\n";

    if (setting('smtp_host') === '') {
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode(mail_clean($subject)) . '?=', $body, implode("\r\n", array_merge(array_slice($headers, 0, 1), array_slice($headers, 3))));
        if (!$ok) mail_last_error('mail() do PHP falhou. Configure um servidor SMTP.');
        return $ok;
    }
    try {
        smtp_deliver($fromAddr, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body);
        return true;
    } catch (Throwable $e) {
        mail_last_error($e->getMessage());
        error_log('SMTP: ' . $e->getMessage());
        return false;
    }
}

function mail_html_wrap(string $text): string
{
    $safe = nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $safe = preg_replace('#(https?://[^\s<]+)#', '<a style="color:#d4af37" href="$1">$1</a>', $safe);
    return '<!doctype html><html><body style="margin:0;background:#07080c;padding:24px;font-family:Segoe UI,Arial,sans-serif">'
        . '<div style="max-width:560px;margin:0 auto;background:#0d0f16;border:1px solid #6b5a1e;border-radius:12px;padding:28px;color:#eceff6;line-height:1.6">'
        . '<div style="font:700 20px Georgia,serif;color:#d4af37;margin-bottom:14px">&#9670; ' . htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8') . '</div>'
        . $safe . '</div></body></html>';
}

function smtp_read($fp): array
{
    $code = 0; $lines = [];
    while (($line = fgets($fp, 1024)) !== false) {
        $lines[] = rtrim($line);
        $code = (int)substr($line, 0, 3);
        if (strlen($line) < 4 || $line[3] !== '-') break;
    }
    if (!$lines) throw new RuntimeException('Servidor SMTP não respondeu (tempo esgotado).');
    return [$code, implode("\n", $lines)];
}
function smtp_cmd($fp, ?string $cmd, array $ok): string
{
    if ($cmd !== null) fwrite($fp, $cmd . "\r\n");
    [$code, $msg] = smtp_read($fp);
    if (!in_array($code, $ok, true)) throw new RuntimeException('SMTP: ' . preg_replace('/\s+/', ' ', $msg));
    return $msg;
}

function smtp_deliver(string $from, string $to, string $message): void
{
    $host = trim(setting('smtp_host'));
    $port = (int)(setting('smtp_port') ?: 587);
    $enc = setting('smtp_enc', 'tls'); // ssl | tls | none
    $user = setting('smtp_user');
    $pass = setting_secret('smtp_pass');
    if (!preg_match('/^[A-Za-z0-9.\-]+$/', $host)) throw new RuntimeException('Servidor SMTP inválido.');

    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("Não foi possível conectar em $host:$port ($errstr)");
    stream_set_timeout($fp, 20);
    try {
        smtp_cmd($fp, null, [220]);
        $ehlo = 'EHLO ' . (preg_replace('/[^A-Za-z0-9.\-]/', '', $_SERVER['SERVER_NAME'] ?? '') ?: 'localhost');
        smtp_cmd($fp, $ehlo, [250]);
        if ($enc === 'tls') {
            smtp_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Falha ao negociar TLS com o servidor SMTP.');
            smtp_cmd($fp, $ehlo, [250]);
        }
        if ($user !== '') {
            smtp_cmd($fp, 'AUTH LOGIN', [334]);
            smtp_cmd($fp, base64_encode($user), [334]);
            smtp_cmd($fp, base64_encode($pass), [235]);
        }
        smtp_cmd($fp, "MAIL FROM:<$from>", [250]);
        smtp_cmd($fp, "RCPT TO:<$to>", [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);
        $msg = preg_replace('/\r\n|\r|\n/', "\r\n", $message);
        $msg = preg_replace('/^\./m', '..', $msg); // dot-stuffing
        fwrite($fp, $msg . "\r\n.\r\n");
        smtp_cmd($fp, null, [250]);
        @smtp_cmd($fp, 'QUIT', [221]);
    } finally {
        fclose($fp);
    }
}

// ---- e-mails transacionais ----
function mail_welcome(array $u): void
{
    mail_send($u['email'], 'Bem-vindo(a) à ' . site_name(), "Olá, {$u['name']}!\n\nSua conta foi criada com sucesso.\nAcesse: " . url('login.php') . "\n\nBons estudos!");
}
function mail_purchase(int $orderId): void
{
    $o = q_one('SELECT o.amount, u.name, u.email, c.title, c.id AS cid FROM orders o JOIN users u ON u.id = o.user_id JOIN courses c ON c.id = o.course_id WHERE o.id = ?', [$orderId]);
    if (!$o) return;
    mail_send($o['email'], 'Acesso liberado: ' . $o['title'], "Olá, {$o['name']}!\n\nSeu acesso ao curso \"{$o['title']}\" foi liberado (valor pago: " . money($o['amount']) . ").\n\nAssistir agora: " . url('curso.php?id=' . $o['cid']));
}
