<?php
declare(strict_types=1);

const CERT_MONTHS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
function date_long(string $d): string { $t = strtotime($d); return date('j', $t) . ' de ' . CERT_MONTHS[(int)date('n', $t) - 1] . ' de ' . date('Y', $t); }

/**
 * Assinatura: aceita foto/scan (JPG/PNG/WEBP, fundo branco ou transparente) e gera um PNG só de "tinta"
 * (preto com transparência), recortado. O tema do certificado colore a tinta (dourado no escuro, azul no claro).
 */
function save_signature(array $f): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) throw new RuntimeException('Assinatura inválida ou maior que 5MB.');
    $info = @getimagesize($f['tmp_name']);
    $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!$info || !isset($loaders[$info[2]]) || $info[0] * $info[1] > 40_000_000) throw new RuntimeException('Use uma imagem JPG, PNG ou WEBP para a assinatura.');
    $src = @$loaders[$info[2]]($f['tmp_name']);
    if (!$src) throw new RuntimeException('Não foi possível ler a imagem da assinatura.');

    $w = imagesx($src); $h = imagesy($src);
    $nw = min(800, $w); $nh = max(1, (int)round($h * $nw / $w));
    $img = imagecreatetruecolor($nw, $nh);
    imagealphablending($img, false); imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
    imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false); imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    $minX = $nw; $minY = $nh; $maxX = 0; $maxY = 0;
    for ($y = 0; $y < $nh; $y++) {
        for ($x = 0; $x < $nw; $x++) {
            $c = imagecolorat($img, $x, $y);
            $opacity = 1 - (($c >> 24) & 127) / 127;                       // transparência original
            $lum = (0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255)) / 255;
            $ink = max(0.0, min(1.0, ((1 - $lum) - 0.12) / 0.6));        // papel claro some, traço escuro fica
            $a = $opacity * $ink;
            if ($a < 0.04) continue;
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 0, 0, 0, (int)round(127 * (1 - $a))));
            if ($x < $minX) $minX = $x; if ($x > $maxX) $maxX = $x; if ($y < $minY) $minY = $y; if ($y > $maxY) $maxY = $y;
        }
    }
    imagedestroy($img);
    if ($maxX <= $minX || $maxY <= $minY) throw new RuntimeException('Não encontrei traços na assinatura. Envie uma imagem com a assinatura em tinta escura sobre fundo claro.');
    $pad = 6;
    $crop = imagecrop($out, ['x' => max(0, $minX - $pad), 'y' => max(0, $minY - $pad), 'width' => min($nw, $maxX - $minX + 2 * $pad), 'height' => min($nh, $maxY - $minY + 2 * $pad)]);
    $name = 'sign_' . bin2hex(random_bytes(10)) . '.png';
    imagesavealpha($crop, true);
    if (!imagepng($crop, ROOT . '/uploads/' . $name, 6)) throw new RuntimeException('Falha ao salvar a assinatura.');
    imagedestroy($out); imagedestroy($crop);
    return $name;
}

/** URL da assinatura já pintada na cor do tema (gerada uma vez e guardada em uploads/). */
function cert_signature_url(string $theme): string
{
    $f = cert_signature_file($theme);
    return $f ? url('uploads/' . basename($f)) : '';
}
/** Caminho do arquivo da assinatura já pintada na cor do tema (ou null). */
function cert_signature_file(string $theme): ?string
{
    $base = basename(setting('cert_signature'));
    if ($base === '' || !preg_match('/^sign_[a-f0-9]+\.png$/', $base) || !is_file(ROOT . '/uploads/' . $base)) return null;
    $theme = $theme === 'light' ? 'light' : 'dark';
    $colored = substr($base, 0, -4) . '_' . $theme . '.png';
    if (!is_file(ROOT . '/uploads/' . $colored)) {
        $src = @imagecreatefrompng(ROOT . '/uploads/' . $base);
        if (!$src) return null;
        [$r, $g, $b] = $theme === 'light' ? [11, 31, 69] : [243, 217, 139];
        $w = imagesx($src); $h = imagesy($src);
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false); imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, $r, $g, $b, 127));
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
            $a = (imagecolorat($src, $x, $y) >> 24) & 127;
            if ($a < 127) imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, $a));
        }
        imagepng($out, ROOT . '/uploads/' . $colored, 6);
        imagedestroy($src); imagedestroy($out);
    }
    return ROOT . '/uploads/' . $colored;
}

/** Logo: aceita JPG/PNG/WEBP, reduz para caber em $maxW x $maxH (nunca amplia) e salva em PNG com transparência. */
function save_logo_image(array $f, string $prefix, int $maxW = 600, int $maxH = 240): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) throw new RuntimeException('Logo inválido ou maior que 5MB.');
    $info = @getimagesize($f['tmp_name']);
    $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!$info || !isset($loaders[$info[2]]) || $info[0] * $info[1] > 40_000_000) throw new RuntimeException('Use um logo em JPG, PNG ou WEBP.');
    $src = @$loaders[$info[2]]($f['tmp_name']);
    if (!$src) throw new RuntimeException('Não foi possível ler a imagem do logo.');
    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, $maxW / $w, $maxH / $h);
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false); imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $name = $prefix . '_' . bin2hex(random_bytes(10)) . '.png';
    $ok = imagepng($out, ROOT . '/uploads/' . $name, 6);
    imagedestroy($src); imagedestroy($out);
    if (!$ok) throw new RuntimeException('Falha ao salvar o logo.');
    return $name;
}

function cert_seal_svg(): string
{
    $pts = [];
    for ($i = 0; $i < 48; $i++) { $r = $i % 2 ? 44 : 48.5; $a = M_PI * 2 * $i / 48; $pts[] = round($r * cos($a), 2) . ',' . round($r * sin($a), 2); }
    $star = [];
    for ($i = 0; $i < 10; $i++) { $r = $i % 2 ? 5.2 : 12; $a = -M_PI / 2 + M_PI * $i / 5; $star[] = round($r * cos($a), 2) . ',' . round($r * sin($a), 2); }
    return '<svg viewBox="-50 -50 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<polygon class="seal-edge" points="' . implode(' ', $pts) . '"/><circle class="seal-core" r="40"/><circle class="seal-ring" r="36.5"/>'
        . '<defs><path id="sealpath" d="M -27,0 a 27,27 0 1,1 54,0 a 27,27 0 1,1 -54,0"/></defs>'
        . '<text class="seal-text"><textPath href="#sealpath" startOffset="0" textLength="166" lengthAdjust="spacing">CERTIFICADO ✦ AUTÊNTICO ✦ </textPath></text>'
        . '<polygon class="seal-star" points="' . implode(' ', $star) . '"/></svg>';
}

/**
 * Renderiza a página do certificado (A4 paisagem). $d: org, cnpj, name, cpf, course, workload, score, completed, issued,
 * code, public_url, signer, signer_role, signature_url, logo_url, theme, back_url, extra_actions(html), title
 */
function cert_render(array $d): void
{
    $theme = ($d['theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
    $stats = [];
    if (!empty($d['workload'])) $stats[] = [$d['workload'], 'Carga horária'];
    if ($d['score'] !== null) $stats[] = [rtrim(rtrim(number_format((float)$d['score'], 2, ',', ''), '0'), ',') . '%', 'Aproveitamento'];
    if (!empty($d['completed'])) $stats[] = [date('d/m/Y', strtotime($d['completed'])), 'Conclusão'];
    $nameLen = mb_strlen($d['name']);
    $nameSize = $nameLen > 34 ? 11 : ($nameLen > 26 ? 13.5 : 17);
    $courseLen = mb_strlen($d['course']);
    $courseSize = $courseLen > 60 ? 5.2 : ($courseLen > 40 ? 6.4 : 8);
    ?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($d['title'] ?? 'Certificado') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/certificate.css')) ?>"></head>
<body class="cp">
<div class="cert-viewport" id="cert-viewport">
<div class="certd theme-<?= $theme ?>" id="cert">
  <div class="c-pattern"></div><div class="c-band"></div>
  <div class="c-frame"><i class="tl"></i><i class="tr"></i><i class="bl"></i><i class="br"></i></div>
  <div class="c-inner">
    <div class="c-head"><?= !empty($d['logo_url']) ? '<img src="' . e($d['logo_url']) . '" alt="">' : '<span class="c-mark"></span>' ?><span class="c-org"><?= e($d['org']) ?></span></div>
    <div class="c-title"><h1 aria-label="Certificado"><svg viewBox="0 0 760 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs><linearGradient id="tg" x1="0" x2="1" y1="0" y2="0"><stop offset="0" class="tg1"/><stop offset=".5" class="tg2"/><stop offset="1" class="tg1"/></linearGradient></defs><text x="380" y="80" text-anchor="middle" fill="url(#tg)">CERTIFICADO</text></svg></h1><div class="c-sub"><span></span>de conclusão<span></span></div></div>
    <p class="c-lead">Certificamos que</p>
    <div class="c-name" style="font-size:<?= $nameSize ?>mm"><?= e($d['name']) ?></div>
    <?php if (!empty($d['cpf'])): ?><div class="c-cpf">CPF <?= e($d['cpf']) ?></div><?php endif; ?>
    <p class="c-lead" style="margin-top:3.2mm">concluiu com êxito o curso</p>
    <div class="c-course" style="font-size:<?= $courseSize ?>mm"><?= e($d['course']) ?></div>
    <?php if ($stats): ?><div class="c-stats"><?php foreach ($stats as [$v, $l]): ?><div><b><?= e($v) ?></b><small><?= e($l) ?></small></div><?php endforeach; ?></div><?php endif; ?>
    <div class="c-foot">
      <div class="c-qr"><div class="qrcard"><?= qr_svg($d['public_url'], 240) ?></div>
        <small><b>Valide este certificado</b><br>Escaneie o QR Code ou acesse<br><?= e(preg_replace('#^https?://#', '', url('verificar.php'))) ?><br>Código <b><?= e(cert_code_display($d['code'])) ?></b></small></div>
      <div class="c-seal"><?= cert_seal_svg() ?></div>
      <div class="c-sign">
        <?php if (!empty($d['signature_url'])): ?><img class="inkimg" src="<?= e($d['signature_url']) ?>" alt="Assinatura">
        <?php elseif (!empty($d['signer'])): ?><div class="script"><?= e($d['signer']) ?></div><?php else: ?><div class="ink"></div><?php endif; ?>
        <div class="line"></div>
        <?php if (!empty($d['signer'])): ?><strong><?= e($d['signer']) ?></strong><?php endif; ?>
        <?php if (!empty($d['signer_role'])): ?><small><?= e($d['signer_role']) ?></small><?php endif; ?>
        <small><?= e($d['org']) ?><?= !empty($d['cnpj']) ? ' · CNPJ ' . e($d['cnpj']) : '' ?></small>
      </div>
    </div>
    <div class="c-meta">Emitido em <?= e(date_long($d['issued'])) ?> <i>◆</i> Autenticidade verificável pelo QR Code</div>
  </div>
</div></div>
<div class="no-print actions">
  <button class="btn" type="button" data-print>&#128424; Imprimir / Salvar em PDF</button>
  <?= $d['extra_actions'] ?? '' ?>
</div>
<?php if (empty($d['hide_tip'])): ?><p class="hint no-print" style="text-align:center;margin-top:10px;color:#8a93ad;font-family:Montserrat,sans-serif">Na janela de impressão escolha <strong>Salvar como PDF</strong>, papel A4, orientação paisagem e margens nenhuma.</p><?php endif; ?>
<script src="<?= e(asset('js/certificate.js')) ?>"></script>
</body></html>
<?php
}
