<?php
declare(strict_types=1);

function current_user(bool $refresh = false): ?array
{
    static $u = false;
    if ($refresh) $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = q_one('SELECT id, name, email, role, status FROM users WHERE id = ?', [$_SESSION['uid']]);
            if ($u && $u['status'] !== 'active') { $u = null; unset($_SESSION['uid']); }
        }
    }
    return $u;
}
function is_admin(): bool { $u = current_user(); return $u && $u['role'] === 'admin'; }

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') $_SESSION['next'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    return $u;
}
function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit('Acesso restrito.'); }
    return $u;
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    current_user(true);
    q('UPDATE users SET last_login = ? WHERE id = ?', [now(), $u['id']]);
}
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function too_many_attempts(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - 900);
    $byIp = (int)q_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [client_ip(), $since]);
    $byEmail = (int)q_val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > ?', [$email, $since]);
    return $byIp >= 10 || $byEmail >= 5;
}
function record_attempt(string $email): void
{
    q('INSERT INTO login_attempts (ip, email, created_at) VALUES (?, ?, ?)', [client_ip(), $email, now()]);
    q('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
}

function password_ok(string $p): ?string
{
    if (strlen($p) < 8) return 'A senha deve ter pelo menos 8 caracteres.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'A senha deve conter letras e números.';
    return null;
}

/** Guarda o destino pós-login vindo de ?next= (validado só na hora de usar). */
function remember_next(): void
{
    if (isset($_GET['next']) && is_string($_GET['next']) && strlen($_GET['next']) < 300) $_SESSION['next'] = $_GET['next'];
}

function safe_next(): string
{
    $n = $_SESSION['next'] ?? '';
    unset($_SESSION['next']);
    $base = BASE_URL_PATH;
    // só aceita caminhos do próprio site (evita redirecionamento aberto); devolve relativo à raiz do site
    if ($n !== '' && $n[0] === '/' && !str_starts_with($n, '//') && !str_contains($n, '\\') && !str_contains($n, '..') && !preg_match('/[\r\n]/', $n) && str_starts_with($n, $base . '/')) {
        return ltrim(substr($n, strlen($base)), '/');
    }
    return is_admin() ? 'admin/' : 'home.php';
}
