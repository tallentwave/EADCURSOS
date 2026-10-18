<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        global $CONFIG;
        $c = $CONFIG['db'];
        $pdo = new PDO(
            "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("SET time_zone = '-03:00', sql_mode = REPLACE(@@sql_mode, 'ANSI_QUOTES', '')");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function q_all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function q_one(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r ?: null; }
function q_val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
function now(): string { return date('Y-m-d H:i:s'); }

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $p = ''): string { return BASE_URL . '/' . ltrim($p, '/'); }
function asset(string $p): string { return url('assets/' . ltrim($p, '/')); }

function redirect(string $p): never
{
    header('Location: ' . (preg_match('#^https?://#', $p) ? $p : url($p)));
    exit;
}

function money($v): string { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }

function setting(string $k, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q_all('SELECT k, v FROM settings') as $r) $cache[$r['k']] = (string)$r['v'];
    }
    return $cache[$k] ?? $default;
}
function set_setting(string $k, string $v): void
{
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
}
function site_name(): string { return setting('site_name', 'EAD Premium'); }

function slugify(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'curso';
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Sessão expirada. Volte e tente novamente.');
    }
}
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido'); }
    csrf_check();
}

// ---------- Flash ----------
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) $out .= '<div class="alert alert-' . e($t) . '">' . e($m) . '</div>';
    unset($_SESSION['flash']);
    return $out;
}

function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45); }

// ---------- Uploads ----------
function save_image(array $f, string $prefix): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) throw new RuntimeException('Imagem inválida ou maior que 5MB.');
    $info = @getimagesize($f['tmp_name']);
    $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($map[$info[2]])) throw new RuntimeException('Use imagens JPG, PNG ou WEBP.');
    $name = $prefix . '_' . bin2hex(random_bytes(10)) . '.' . $map[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], ROOT . '/uploads/' . $name)) throw new RuntimeException('Falha ao salvar a imagem.');
    return $name;
}
function img_url(?string $name, string $fallback = 'img/placeholder.svg'): string
{
    return $name ? url('uploads/' . $name) : asset($fallback);
}

// ---------- Vídeo ----------
/** Retorna URL de embed segura (somente YouTube/Vimeo) a partir do link colado pelo admin. */
function video_embed(string $u): ?string
{
    $u = trim($u);
    if (preg_match('#(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $u, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1';
    }
    if (preg_match('#vimeo\.com/(?:video/)?(\d+)(?:/([a-f0-9]+))?#', $u, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1] . (!empty($m[2]) ? '?h=' . $m[2] : '');
    }
    return null;
}

// ---------- Segredos criptografados no banco (AES-256-GCM, chave derivada do app_key) ----------
function secret_encrypt(string $plain): string
{
    if ($plain === '') return '';
    $key = hash('sha256', APP_KEY, true);
    $iv = random_bytes(12);
    $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return 'enc:' . base64_encode($iv . $tag . $ct);
}
function secret_decrypt(string $stored): string
{
    if (!str_starts_with($stored, 'enc:')) return $stored; // valores antigos em texto puro
    $raw = base64_decode(substr($stored, 4), true);
    if ($raw === false || strlen($raw) < 29) return '';
    $key = hash('sha256', APP_KEY, true);
    $pt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $pt === false ? '' : $pt;
}
function setting_secret(string $k): string { return secret_decrypt(setting($k)); }
function set_secret(string $k, string $v): void { set_setting($k, secret_encrypt($v)); }
