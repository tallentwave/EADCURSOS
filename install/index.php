<?php
declare(strict_types=1);
/**
 * Instalador automático. Cria banco/tabelas, administrador e o arquivo config/config.php.
 * Após instalar ele se bloqueia (config/installed.lock) — apague a pasta /install por segurança.
 */
session_name('EADINSTALL');
session_start();

$root = dirname(__DIR__);
$lock = $root . '/config/installed.lock';
$cfgFile = $root . '/config/config.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if (is_file($lock) || is_file($cfgFile)) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><body style="background:#07080c;color:#eee;font-family:sans-serif;text-align:center;padding:80px"><h1 style="color:#d4af37">Sistema já instalado</h1><p>Por segurança, apague a pasta <code>/install</code> do servidor.</p></body>';
    exit;
}

$checks = [
    'PHP 8.0 ou superior (atual: ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'Extensão PDO MySQL' => extension_loaded('pdo_mysql'),
    'Extensão cURL (Mercado Pago)' => extension_loaded('curl'),
    'Extensão OpenSSL' => extension_loaded('openssl'),
    'Extensão mbstring' => extension_loaded('mbstring'),
    'Extensão fileinfo' => extension_loaded('fileinfo'),
    'Pasta config/ gravável' => is_writable($root . '/config'),
    'Pasta uploads/ gravável' => is_writable($root . '/uploads'),
    'Pasta storage/materials/ gravável' => is_writable($root . '/storage/materials'),
];
$ok = !in_array(false, $checks, true);

if (empty($_SESSION['t'])) $_SESSION['t'] = bin2hex(random_bytes(24));
$err = '';
$v = $_POST + ['db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'site_name' => 'EAD Premium', 'admin_name' => '', 'admin_email' => '', 'demo' => '1'];

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$guess = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$v['base_url'] = $_POST['base_url'] ?? $guess;

if ($ok && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['t'], (string)($_POST['t'] ?? ''))) throw new RuntimeException('Sessão inválida. Recarregue a página.');
        $host = trim($_POST['db_host']); $name = trim($_POST['db_name']); $user = trim($_POST['db_user']); $pass = (string)$_POST['db_pass'];
        $aname = trim($_POST['admin_name']); $aemail = strtolower(trim($_POST['admin_email'])); $apass = (string)$_POST['admin_pass'];
        $base = rtrim(trim($_POST['base_url']), '/');
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) throw new RuntimeException('Nome do banco inválido (use letras, números e _).');
        if (!preg_match('#^https?://#', $base)) throw new RuntimeException('URL base inválida.');
        if ($aname === '' || !filter_var($aemail, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe nome e e-mail válidos do administrador.');
        if (strlen($apass) < 8 || !preg_match('/[A-Za-z]/', $apass) || !preg_match('/\d/', $apass)) throw new RuntimeException('A senha do admin precisa de 8+ caracteres com letras e números.');
        if ($apass !== $_POST['admin_pass2']) throw new RuntimeException('As senhas do administrador não conferem.');

        $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, $opts);
        } catch (PDOException $e) {
            // tenta criar o banco (em hospedagem compartilhada normalmente ele já existe)
            try {
                $tmp = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, $opts);
                $tmp->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, $opts);
            } catch (PDOException $e2) {
                throw new RuntimeException('Não foi possível conectar ao banco: ' . $e2->getMessage());
            }
        }
        if ($pdo->query("SHOW TABLES LIKE 'users'")->fetch()) throw new RuntimeException('O banco já contém tabelas do sistema. Use um banco vazio.');

        foreach (array_filter(array_map('trim', explode(";\n", file_get_contents(__DIR__ . '/schema.sql')))) as $sql) $pdo->exec($sql);

        $now = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?,?,?,"admin",?)')
            ->execute([$aname, $aemail, password_hash($apass, PASSWORD_DEFAULT), $now]);
        $pdo->prepare('INSERT INTO settings (k, v) VALUES (\'site_name\', ?), (\'mp_sandbox\', \'0\'), (\'schema_version\', \'4\')')->execute([trim($_POST['site_name']) ?: 'EAD Premium']);

        if (!empty($_POST['demo'])) {
            $pdo->prepare('INSERT INTO courses (title, slug, category, short_desc, description, price, featured, status, created_at) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute(['Curso de Demonstração', 'curso-de-demonstracao', 'Demonstração', 'Exemplo de curso para testar a plataforma.', "Este é um curso de exemplo.\nEdite ou exclua pelo painel administrativo.", 97.00, 1, 'published', $now]);
            $cid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO modules (course_id, title, position) VALUES (?, ?, 1)')->execute([$cid, 'Módulo 1 — Introdução']);
            $mid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO lessons (module_id, title, description, video_url, duration_min, position, is_free) VALUES (?,?,?,?,?,?,?)');
            $ins->execute([$mid, 'Boas-vindas', 'Aula gratuita de apresentação.', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 10, 1, 1]);
            $ins->execute([$mid, 'Primeiros passos', 'Conteúdo exclusivo para alunos.', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 15, 2, 0]);
        }

        $cfg = "<?php\nreturn " . var_export([
            'db' => ['host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass],
            'base_url' => $base,
            'app_key' => bin2hex(random_bytes(32)),
            'debug' => false,
        ], true) . ";\n";
        if (file_put_contents($cfgFile, $cfg, LOCK_EX) === false) throw new RuntimeException('Não foi possível gravar config/config.php.');
        @chmod($cfgFile, 0640);
        file_put_contents($lock, 'Instalado em ' . $now);
        session_destroy();
        header('Location: ' . $base . '/login.php');
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalador · EAD Premium</title>
<style>
:root{--gold:#d4af37}*{box-sizing:border-box}
body{margin:0;background:radial-gradient(1000px 500px at 10% -10%,#0f2a5e,transparent 60%),#07080c;color:#eceff6;font:16px/1.5 "Segoe UI",system-ui,sans-serif;padding:30px 16px}
.box{max-width:720px;margin:0 auto;background:#0d0f16;border:1px solid rgba(212,175,55,.3);border-radius:14px;padding:34px}
h1{font-family:Georgia,serif;color:var(--gold);margin:0 0 4px}h2{color:var(--gold);font-size:1.1rem;margin:26px 0 4px;border-bottom:1px solid rgba(212,175,55,.25);padding-bottom:6px}
label{display:block;margin:12px 0 5px;font-size:.88rem;color:#9aa4bd}
input[type=text],input[type=password],input[type=email]{width:100%;padding:11px 13px;border-radius:7px;border:1px solid #26304d;background:#0b1020;color:#eceff6;font:inherit}
input:focus{outline:2px solid var(--gold)}.g{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
button{margin-top:24px;width:100%;border:0;padding:14px;border-radius:7px;font:700 1rem inherit;background:linear-gradient(135deg,#d4af37,#a8841f);color:#111;cursor:pointer}
.err{background:rgba(229,72,77,.12);border:1px solid #e5484d;padding:12px;border-radius:8px;margin:14px 0}
.ck{font-size:.9rem;margin:3px 0}.y{color:#3ecf8e}.n{color:#e5484d}.h{font-size:.8rem;color:#9aa4bd}
@media(max-width:600px){.g{grid-template-columns:1fr}}
</style></head><body><div class="box">
<h1>&#9670; Instalador</h1><div class="h">Plataforma de cursos EAD · PHP + MySQL</div>
<h2>1. Requisitos do servidor</h2>
<?php foreach ($checks as $k => $r): ?><div class="ck"><span class="<?= $r ? 'y' : 'n' ?>"><?= $r ? '&#10003;' : '&#10007;' ?></span> <?= h($k) ?></div><?php endforeach; ?>
<?php if (!$ok): ?><div class="err">Corrija os itens em vermelho (permissões de pasta: chmod 755/775) e recarregue.</div><?php else: ?>
<?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
<form method="post" autocomplete="off"><input type="hidden" name="t" value="<?= h($_SESSION['t']) ?>">
<h2>2. Banco de dados MySQL</h2>
<div class="g"><div><label>Servidor</label><input type="text" name="db_host" value="<?= h($v['db_host']) ?>" required></div>
<div><label>Nome do banco</label><input type="text" name="db_name" value="<?= h($v['db_name']) ?>" required></div>
<div><label>Usuário</label><input type="text" name="db_user" value="<?= h($v['db_user']) ?>" required></div>
<div><label>Senha</label><input type="password" name="db_pass"></div></div>
<h2>3. Plataforma</h2>
<label>Nome da plataforma</label><input type="text" name="site_name" value="<?= h($v['site_name']) ?>" required>
<label>URL de instalação (sem barra no final; use https em produção)</label><input type="text" name="base_url" value="<?= h($v['base_url']) ?>" required>
<h2>4. Administrador</h2>
<div class="g"><div><label>Nome</label><input type="text" name="admin_name" value="<?= h($v['admin_name']) ?>" required></div>
<div><label>E-mail (login)</label><input type="email" name="admin_email" value="<?= h($v['admin_email']) ?>" required></div>
<div><label>Senha (8+, letras e números)</label><input type="password" name="admin_pass" required minlength="8"></div>
<div><label>Repita a senha</label><input type="password" name="admin_pass2" required></div></div>
<label style="margin-top:18px;color:#eceff6"><input type="checkbox" name="demo" value="1" <?= $v['demo'] ? 'checked' : '' ?>> Criar curso de demonstração</label>
<button>Instalar agora</button></form><?php endif; ?>
</div></body></html>
