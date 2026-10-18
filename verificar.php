<?php
// Página pública de validação. Mostra o mínimo necessário (LGPD): nunca e-mail, CPF completo, telefone, IP ou notas por módulo.
require __DIR__ . '/app/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');

$raw = trim((string)($_GET['codigo'] ?? ''));
$code = $raw !== '' ? cert_code_normalize($raw) : '';
$cert = null; $msg = '';
if ($raw !== '') {
    if (too_many_attempts('ver:' . client_ip())) {
        $msg = 'Muitas consultas seguidas. Aguarde alguns minutos e tente novamente.';
    } elseif ($code === '') {
        record_attempt('ver:' . client_ip());
        $msg = 'Código em formato inválido. Ele tem 12 ou 16 caracteres (letras A–F e números).';
    } else {
        $cert = q_one('SELECT * FROM certificates WHERE code = ?', [$code]);
        if (!$cert) { record_attempt('ver:' . client_ip()); $msg = 'Nenhum certificado encontrado com este código.'; }
    }
}

/** "Maria da Silva Souza" => "Maria S." (modo reservado) */
function short_name(string $n): string
{
    $p = preg_split('/\s+/', trim($n)) ?: [];
    return count($p) > 1 ? $p[0] . ' ' . mb_strtoupper(mb_substr(end($p), 0, 1)) . '.' : ($p[0] ?? '');
}
$org = setting('company_name') ?: site_name();
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Validação de certificado · <?= e(site_name()) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body>
<header class="nav pub-nav"><a class="brand" href="<?= e(url('')) ?>"><span>&#9670;</span> <?= e(site_name()) ?></a></header>
<main><div class="pub-wrap">
<h1 style="color:var(--gold);margin-bottom:6px">Validação de certificado</h1>
<p class="hint" style="margin-bottom:18px">Confirme a autenticidade de um certificado emitido por <?= e($org) ?>.</p>

<?php if ($cert): $revoked = $cert['status'] === 'revoked'; $full = (bool)$cert['show_details']; ?>
  <div class="pub-card">
    <?php if ($revoked): ?>
      <div class="pub-seal bad"><span class="ic">&#9888;</span><span>Certificado REVOGADO<br><small style="font-weight:400">Este certificado não é mais válido<?= $cert['revoked_at'] ? ' (revogado em ' . e(date('d/m/Y', strtotime($cert['revoked_at']))) . ')' : '' ?>.</small></span></div>
    <?php else: ?>
      <div class="pub-seal ok"><span class="ic">&#10003;</span><span>Certificado autêntico e válido<br><small style="font-weight:400">Emitido por <?= e($org) ?></small></span></div>
    <?php endif; ?>
    <dl class="pub-dl">
      <?php if (!$revoked): ?>
        <dt>Titular</dt><dd><?= e($full ? ($cert['holder_name'] ?: '—') : short_name((string)$cert['holder_name'])) ?></dd>
        <?php if ($full && $cert['holder_cpf_masked']): ?><dt>CPF</dt><dd><?= e($cert['holder_cpf_masked']) ?></dd><?php endif; ?>
      <?php endif; ?>
      <dt>Curso</dt><dd><?= e($cert['course_title'] ?: '—') ?></dd>
      <?php if (!$revoked && (int)$cert['workload_min'] > 0): ?><dt>Carga horária</dt><dd><?= e(fmt_workload((int)$cert['workload_min'])) ?></dd><?php endif; ?>
      <?php if (!$revoked && $full && $cert['final_score'] !== null): ?><dt>Aproveitamento final</dt><dd><?= rtrim(rtrim(number_format((float)$cert['final_score'], 2, ',', ''), '0'), ',') ?>%</dd><?php endif; ?>
      <?php if (!$revoked && $cert['completed_at']): ?><dt>Conclusão</dt><dd><?= e(date('d/m/Y', strtotime($cert['completed_at']))) ?></dd><?php endif; ?>
      <dt>Emissão</dt><dd><?= e(date('d/m/Y', strtotime($cert['issued_at']))) ?></dd>
      <dt>Instituição</dt><dd><?= e($org) ?><?= setting('company_cnpj') ? ' · CNPJ ' . e(setting('company_cnpj')) : '' ?></dd>
      <dt>Código</dt><dd><?= e(cert_code_display($cert['code'])) ?></dd>
    </dl>
    <p class="hint" style="margin-top:18px">&#128274; Por proteção de dados (LGPD), esta página não exibe e-mail, CPF completo nem outros dados pessoais do titular.
      <?= $full ? '' : 'O titular optou por manter o nome abreviado e o CPF oculto. ' ?><a href="<?= e(url('privacidade.php')) ?>">Saiba mais</a>.</p>
  </div>
  <p style="margin-top:16px"><a class="btn btn-ghost btn-sm" href="<?= e(url('verificar.php')) ?>">Consultar outro código</a></p>
<?php else: ?>
  <?php if ($msg): ?><div class="alert alert-err" style="margin:0 0 16px"><?= e($msg) ?></div><?php endif; ?>
  <form class="formbox" method="get"><label>Código do certificado (impresso no certificado, ao lado do QR Code)</label>
    <input type="text" name="codigo" maxlength="24" value="<?= e($raw) ?>" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off" style="text-transform:uppercase;letter-spacing:2px"><br><button class="btn">Validar</button></form>
<?php endif; ?>
</div></main>
<footer class="foot">&copy; <?= date('Y') ?> <?= e(site_name()) ?> · <a href="<?= e(url('privacidade.php')) ?>">Política de Privacidade</a></footer>
</body></html>
