<?php
declare(strict_types=1);

// Carteirinha de capacitação (cartão 85,6 x 54 mm a 300 dpi), desenhada no servidor com GD.
const CARD_W = 1012;
const CARD_H = 638;

// ---------- Foto do aluno (privada: fica fora da pasta pública e só é servida pelo próprio dono) ----------
function photo_dir(): string
{
    $d = ROOT . '/storage/photos';
    if (!is_dir($d) && !@mkdir($d, 0750, true)) throw new RuntimeException('Não foi possível criar a pasta de fotos (verifique as permissões de storage/).');
    return $d;
}
function user_photo_path(?string $name): ?string
{
    $name = basename((string)$name);
    $p = ROOT . '/storage/photos/' . $name;
    return $name !== '' && is_file($p) ? $p : null;
}
/** Conteúdo JPEG da foto (descriptografado em memória) ou null. */
function user_photo_bytes(?string $name): ?string
{
    $p = user_photo_path($name);
    return $p ? blob_decrypt((string)file_get_contents($p)) : null;
}

/** Recorta em 3:4 (enquadrando um pouco acima do centro, onde costuma ficar o rosto), corrige a rotação do celular e remove EXIF. */
function save_user_photo(array $f, int $uid): string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Escolha uma foto.');
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 10 * 1024 * 1024) throw new RuntimeException('Foto inválida ou maior que 10MB.');
    $info = @getimagesize($f['tmp_name']);
    $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!$info || !isset($loaders[$info[2]]) || $info[0] * $info[1] > 50_000_000) throw new RuntimeException('Use uma foto em JPG, PNG ou WEBP.');
    $src = @$loaders[$info[2]]($f['tmp_name']);
    if (!$src) throw new RuntimeException('Não foi possível ler a foto.');

    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($f['tmp_name']);
        $rot = [3 => 180, 6 => -90, 8 => 90][$ex['Orientation'] ?? 1] ?? 0;
        if ($rot) { $r = imagerotate($src, $rot, 0); if ($r) { imagedestroy($src); $src = $r; } }
    }
    $w = imagesx($src); $h = imagesy($src);
    if ($w < 240 || $h < 320) throw new RuntimeException('Foto muito pequena. Use uma imagem com pelo menos 240 x 320 pixels.');

    // maior retângulo 3:4 possível
    $cw = min($w, (int)floor($h * 3 / 4)); $ch = (int)floor($cw * 4 / 3);
    $cx = (int)floor(($w - $cw) / 2);
    $cy = (int)max(0, min($h - $ch, floor(($h - $ch) * 0.3)));
    $out = imagecreatetruecolor(600, 800);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $src, 0, 0, $cx, $cy, 600, 800, $cw, $ch);
    imagedestroy($src);

    $dir = photo_dir();
    $name = 'u' . $uid . '_' . bin2hex(random_bytes(10)) . '.bin';
    ob_start(); imagejpeg($out, null, 88); $jpg = (string)ob_get_clean();
    imagedestroy($out);
    if ($jpg === '' || file_put_contents($dir . '/' . $name, blob_encrypt($jpg), LOCK_EX) === false) throw new RuntimeException('Falha ao salvar a foto.');
    @chmod($dir . '/' . $name, 0640);
    return $name;
}
function delete_user_photo(int $uid): void
{
    $old = q_val('SELECT photo FROM users WHERE id = ?', [$uid]);
    if ($old && ($p = user_photo_path($old))) @unlink($p);
    q('UPDATE users SET photo = NULL, photo_consent_at = NULL WHERE id = ?', [$uid]);
}

// ---------- Dados da carteirinha ----------
/** Certificado válido do aluno naquele curso (a carteirinha só existe junto com um certificado ativo). */
function card_cert(int $uid, int $courseId): ?array
{
    return q_one('SELECT * FROM certificates WHERE user_id = ? AND course_id = ? AND status = "valid"', [$uid, $courseId]);
}
function card_data(array $cert, ?string $photoJpeg): array
{
    $theme = setting('cert_theme', 'dark') === 'light' ? 'light' : 'dark';
    return [
        'theme' => $theme, 'org' => setting('company_name') ?: site_name(), 'cnpj' => setting('company_cnpj'),
        'name' => (string)$cert['holder_name'], 'cpf' => $cert['show_details'] ? (string)$cert['holder_cpf_masked'] : '',
        'course' => (string)$cert['course_title'], 'workload' => fmt_workload((int)$cert['workload_min']),
        'completed' => $cert['completed_at'] ? date('d/m/Y', strtotime($cert['completed_at'])) : '', 'issued' => date('d/m/Y', strtotime($cert['issued_at'])),
        'code' => (string)$cert['code'], 'public_url' => cert_public_url($cert['code']),
        'signer' => setting('cert_signer_name'), 'signer_role' => setting('cert_signer_role'),
        'signature_file' => cert_signature_file($theme), 'logo_file' => card_logo_file(), 'photo' => $photoJpeg,
    ];
}
function card_logo_file(): ?string
{
    $f = basename(setting('cert_logo') ?: setting('site_logo'));
    $p = ROOT . '/uploads/' . $f;
    return $f !== '' && is_file($p) ? $p : null;
}

// ---------- Desenho (GD) ----------
function card_palette(string $theme): array
{
    return $theme === 'light'
        ? ['bg1' => [255, 253, 247], 'bg2' => [226, 234, 250], 'ink' => [11, 31, 69], 'muted' => [88, 100, 127], 'gold' => [166, 127, 21], 'gold2' => [199, 159, 44], 'frame' => [166, 127, 21]]
        : ['bg1' => [5, 7, 13], 'bg2' => [13, 38, 80], 'ink' => [238, 241, 248], 'muted' => [159, 169, 196], 'gold' => [212, 175, 55], 'gold2' => [243, 217, 139], 'frame' => [212, 175, 55]];
}
function card_font(string $f): string { return ROOT . '/assets/fonts/' . $f; }

function card_text_w(float $size, string $font, string $text): float
{
    $b = imagettfbbox($size, 0, $font, $text);
    return $b ? abs($b[2] - $b[0]) : 0.0;
}
/** Texto com espaçamento entre letras e "negrito" opcional. Retorna a largura desenhada. */
function card_text($im, float $size, float $x, float $y, int $color, string $font, string $text, float $spacing = 0, bool $bold = false): float
{
    $x = (int)round($x); $y = (int)round($y);
    // negrito real quando a fonte tem versão bold (Lato); nas demais, simula repetindo o traço
    if ($bold && str_ends_with($font, 'Lato-Regular.ttf')) { $font = str_replace('Regular', 'Bold', $font); $bold = false; }
    if ($spacing <= 0) {
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
        if ($bold) imagettftext($im, $size, 0, $x + 1, $y, $color, $font, $text);
        return card_text_w($size, $font, $text);
    }
    $cx = (float)$x;
    foreach (mb_str_split($text) as $ch) {
        if ($ch === ' ') { $cx += $size * 0.38 + $spacing; continue; }
        imagettftext($im, $size, 0, (int)round($cx), $y, $color, $font, $ch);
        if ($bold) imagettftext($im, $size, 0, (int)round($cx) + 1, $y, $color, $font, $ch);
        $cx += card_text_w($size, $font, $ch) + $spacing + $size * 0.06;
    }
    return $cx - $x;
}
function card_spaced_w(float $size, string $font, string $text, float $spacing): float
{
    $w = 0.0;
    foreach (mb_str_split($text) as $ch) $w += $ch === ' ' ? $size * 0.38 + $spacing : card_text_w($size, $font, $ch) + $spacing + $size * 0.06;
    return $w;
}
/** Quebra em linhas e reduz o corpo até caber. @return array{0:float,1:string[]} */
function card_fit(string $text, string $font, float $max, float $min, int $maxW, int $maxLines): array
{
    for ($s = $max; $s >= $min; $s -= 1) {
        $lines = []; $cur = '';
        foreach (preg_split('/\s+/', trim($text)) as $word) {
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if (card_text_w($s, $font, $try) <= $maxW || $cur === '') $cur = $try; else { $lines[] = $cur; $cur = $word; }
        }
        $lines[] = $cur;
        $ok = count($lines) <= $maxLines;
        foreach ($lines as $l) if (card_text_w($s, $font, $l) > $maxW) $ok = false;
        if ($ok) return [$s, $lines];
    }
    $lines = array_slice($lines, 0, $maxLines);
    $last = rtrim($lines[$maxLines - 1], ' .,') . '…';
    while (card_text_w($min, $font, $last) > $maxW && mb_strlen($last) > 2) $last = mb_substr($last, 0, -2) . '…';
    $lines[$maxLines - 1] = $last;
    return [$min, $lines];
}
/** Quebra em linhas com corpo fixo. @return string[] */
function card_wrap(string $text, string $font, float $size, int $maxW): array
{
    $lines = []; $cur = '';
    foreach (preg_split('/\s+/', trim($text)) as $word) {
        $try = $cur === '' ? $word : $cur . ' ' . $word;
        if (card_text_w($size, $font, $try) <= $maxW || $cur === '') $cur = $try; else { $lines[] = $cur; $cur = $word; }
    }
    $lines[] = $cur;
    return $lines;
}
function card_rrect($im, int $x1, int $y1, int $x2, int $y2, int $r, int $col): void
{
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $col);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $col);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $col);
}
function card_mix(array $a, array $b, float $t): array { return [(int)round($a[0] + ($b[0] - $a[0]) * $t), (int)round($a[1] + ($b[1] - $a[1]) * $t), (int)round($a[2] + ($b[2] - $a[2]) * $t)]; }

function card_background($im, array $p): void
{
    for ($x = 0; $x < CARD_W; $x++) {
        $t = pow($x / CARD_W, 1.6) * 0.95;
        [$r, $g, $b] = card_mix($p['bg1'], $p['bg2'], $t);
        imageline($im, $x, 0, $x, CARD_H, imagecolorallocate($im, $r, $g, $b));
    }
    for ($y = 0; $y < CARD_H; $y++) { // faixa dourada à esquerda
        [$r, $g, $b] = card_mix($p['gold2'], $p['gold'], $y / CARD_H);
        imageline($im, 0, $y, 25, $y, imagecolorallocate($im, $r, $g, $b));
    }
    $fr = imagecolorallocatealpha($im, $p['frame'][0], $p['frame'][1], $p['frame'][2], 70);
    imagesetthickness($im, 2);
    imagerectangle($im, 44, 22, CARD_W - 22, CARD_H - 22, $fr);
    imagesetthickness($im, 1);
}
function card_logo($im, ?string $logoFile, int $x, int $y, int $maxH, int $maxW): int
{
    if (!$logoFile) return 0;
    $l = @imagecreatefrompng($logoFile);
    if (!$l) return 0;
    $w = imagesx($l); $h = imagesy($l);
    $s = min($maxH / $h, $maxW / $w, 1.0 * 3);
    $nw = max(1, (int)round($w * $s)); $nh = max(1, (int)round($h * $s));
    imagealphablending($im, true);
    imagecopyresampled($im, $l, $x, $y + (int)(($maxH - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
    imagedestroy($l);
    return $nw;
}

/** @return \GdImage */
function card_render(array $d, string $face)
{
    $im = imagecreatetruecolor(CARD_W, CARD_H);
    imagealphablending($im, true);
    $p = card_palette($d['theme']);
    $col = fn(array $rgb) => imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    $ink = $col($p['ink']); $muted = $col($p['muted']); $gold = $col($p['gold']); $gold2 = $col($p['gold2']);
    $cinzel = card_font('Cinzel.ttf'); $mont = card_font('Lato-Regular.ttf'); $vibes = card_font('GreatVibes.ttf');
    $line = imagecolorallocatealpha($im, $p['frame'][0], $p['frame'][1], $p['frame'][2], 85);
    card_background($im, $p);

    // cabeçalho (logo + instituição)
    $x = 70;
    $lw = card_logo($im, $d['logo_file'], $x, 36, 56, 170);
    if ($lw) $x += $lw + 18;
    else {
        imagefilledpolygon($im, [$x + 24, 38, $x + 48, 62, $x + 24, 86, $x, 62], $gold);
        imagefilledpolygon($im, [$x + 24, 52, $x + 34, 62, $x + 24, 72, $x + 14, 62], imagecolorallocate($im, $p['bg1'][0], $p['bg1'][1], $p['bg1'][2]));
        $x += 66;
    }
    // topo: nome curto da plataforma (o nome jurídico completo e o CNPJ vão no verso)
    $tag = $face === 'front' ? 'CARTEIRA DE CAPACITAÇÃO' : 'VALIDAÇÃO';
    $tagX = CARD_W - 62 - card_spaced_w(16, $mont, $tag, 3.5);
    card_text($im, 16, $tagX, 72, $muted, $mont, $tag, 3.5);
    $org = mb_strtoupper(site_name()); $maxOrgW = (int)($tagX - 28 - $x);
    $osz = 22.0; while ($osz > 15 && card_spaced_w($osz, $mont, $org, 2.5) > $maxOrgW) $osz -= 0.5;
    if (card_spaced_w($osz, $mont, $org, 2.5) <= $maxOrgW) {
        card_text($im, $osz, $x, 72, $gold2, $mont, $org, 2.5, true);
    } else { // nome muito longo: duas linhas
        $ol = array_slice(card_wrap($org, $mont, 15, (int)($maxOrgW * 0.78)), 0, 2);
        $oy = 58; foreach ($ol as $o) { card_text($im, 15, $x, $oy, $gold2, $mont, $o, 1.6, true); $oy += 24; }
    }
    imageline($im, 70, 106, CARD_W - 62, 106, $line);

    if ($face === 'front') {
        // foto 3x4
        $px = 70; $py = 132; $pw = 236; $ph = 315;
        imagefilledrectangle($im, $px - 5, $py - 5, $px + $pw + 4, $py + $ph + 4, $gold);
        $photo = $d['photo'] ? @imagecreatefromstring($d['photo']) : null;
        if ($photo) { imagecopyresampled($im, $photo, $px, $py, 0, 0, $pw, $ph, imagesx($photo), imagesy($photo)); imagedestroy($photo); }
        else { imagefilledrectangle($im, $px, $py, $px + $pw, $py + $ph, imagecolorallocate($im, 40, 48, 70)); card_text($im, 26, $px + 56, $py + 170, $muted, $mont, 'FOTO 3x4'); }

        // código sob a foto
        card_text($im, 12, 70, 500, $muted, $mont, 'CÓDIGO DE VERIFICAÇÃO', 2.0);
        $codeTxt = cert_code_display($d['code']);
        $kz = 19.0; while ($kz > 12 && card_spaced_w($kz, $mont, $codeTxt, 0.6) > 262) $kz -= 0.5;
        card_text($im, $kz, 70, 530, $ink, $mont, $codeTxt, 0.6, true);

        $rx = 346; $rw = CARD_W - 62 - $rx;
        card_text($im, 16, $rx, 150, $muted, $mont, 'ALUNO(A)', 3.5);
        [$ns, $nl] = card_fit(mb_strtoupper($d['name']), $cinzel, 52, 28, $rw, 2);
        $y = 150 + (int)($ns * 1.3);
        foreach ($nl as $ln) { card_text($im, $ns, $rx, $y, $ink, $cinzel, $ln, 0, true); $y += (int)($ns * 1.22); }
        if ($d['cpf'] !== '') { $y -= (int)($ns * 0.25); card_text($im, 22, $rx, $y, $muted, $mont, 'CPF ' . $d['cpf'], 1); $y += 14; }
        $y += 8;
        imagefilledrectangle($im, $rx, $y, $rx + 150, $y + 2, $gold);
        $y += 40;
        card_text($im, 16, $rx, $y, $muted, $mont, 'CAPACITADO(A) EM', 3.5);
        [$cs, $cl] = card_fit($d['course'], $cinzel, 40, 24, $rw, 2);
        $y += (int)($cs * 1.4);
        foreach ($cl as $ln) { card_text($im, $cs, $rx, $y, $gold2, $cinzel, $ln, 0, true); $y += (int)($cs * 1.25); }

        // dados no rodapé da coluna
        $fx = $rx; $fy = 536;
        $cols = array_values(array_filter([['CARGA HORÁRIA', $d['workload']], ['CONCLUSÃO', $d['completed']], ['EMISSÃO', $d['issued']]], fn($c) => $c[1] !== ''));
        $vs = 28.0; $gap = 36;
        $colW = fn(float $v) => array_map(fn($c) => max(card_spaced_w(13, $mont, $c[0], 2.4), card_text_w($v, $mont, $c[1]) * 1.06), $cols);
        while ($vs > 16 && array_sum($colW($vs)) + $gap * max(0, count($cols) - 1) > $rw) $vs -= 1;
        foreach ($cols as $i => [$lab, $val]) {
            card_text($im, 13, $fx, $fy, $muted, $mont, $lab, 2.4);
            card_text($im, $vs, $fx, $fy + 38, $ink, $mont, $val, 0, true);
            $fx += $colW($vs)[$i] + $gap;
        }
    } else {
        // QR Code
        $qr = QRCode::getMinimumQRCode($d['public_url'], QR_ERROR_CORRECT_LEVEL_M);
        $n = $qr->getModuleCount(); $q = 4; $m = (int)floor(372 / ($n + 2 * $q));
        $side = $m * ($n + 2 * $q); $qx = 70; $qy = 132;
        imagefilledrectangle($im, $qx - 8, $qy - 8, $qx + $side + 7, $qy + $side + 7, imagecolorallocate($im, 255, 255, 255));
        $black = imagecolorallocate($im, 0, 0, 0);
        for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) if ($qr->isDark($r, $c)) {
            $x1 = $qx + ($c + $q) * $m; $y1 = $qy + ($r + $q) * $m;
            imagefilledrectangle($im, $x1, $y1, $x1 + $m - 1, $y1 + $m - 1, $black);
        }
        $qb = $qy + $side + 8;
        $y = $qb + 34;
        foreach (card_wrap('Documento pessoal e intransferível. A autenticidade é confirmada somente pela página de validação.', $mont, 15, $side + 14) as $ln) { card_text($im, 15, $qx - 8, $y, $muted, $mont, $ln); $y += 22; }

        $rx = $qx + $side + 52; $rw = CARD_W - 62 - $rx;
        card_text($im, 16, $rx, 150, $muted, $mont, 'COMO VALIDAR', 3.5);
        $y = 192;
        foreach (card_wrap('Escaneie o QR Code com a câmera do celular ou acesse:', $mont, 24, $rw) as $ln) { card_text($im, 24, $rx, $y, $ink, $mont, $ln); $y += 33; }
        [$hs, $hl] = card_fit(preg_replace('#^https?://#', '', url('verificar.php')), $mont, 26, 14, $rw, 2);
        $y += 4; foreach ($hl as $ln) { card_text($im, $hs, $rx, $y, $gold2, $mont, $ln, 0, true); $y += (int)($hs * 1.4); }
        $y += 22;
        card_text($im, 14, $rx, $y, $muted, $mont, 'CÓDIGO DE VERIFICAÇÃO', 2.8);
        $codeTxt = cert_code_display($d['code']);
        $cs = 34.0; while ($cs > 16 && card_spaced_w($cs, $mont, $codeTxt, 1.2) > $rw) $cs -= 1;
        card_text($im, $cs, $rx, $y + 46, $gold, $mont, $codeTxt, 1.2, true);

        // assinatura
        $ly = 528; $sx = $rx;
        if ($d['signature_file'] && ($sg = @imagecreatefrompng($d['signature_file']))) {
            $sw = imagesx($sg); $sh = imagesy($sg); $sc = min(66 / $sh, min(300, $rw) / $sw);
            $nw = max(1, (int)round($sw * $sc)); $nh = max(1, (int)round($sh * $sc));
            imagecopyresampled($im, $sg, $sx, $ly - $nh - 2, 0, 0, $nw, $nh, $sw, $sh); imagedestroy($sg);
        } elseif ($d['signer'] !== '') {
            card_text($im, 44, $sx, $ly - 8, $gold2, $vibes, $d['signer']);
        }
        imagefilledrectangle($im, $sx, $ly, $sx + min(320, $rw), $ly + 1, $gold);
        if ($d['signer'] !== '') card_text($im, 21, $sx, $ly + 30, $ink, $mont, $d['signer'], 0, true);
        if ($d['signer_role'] !== '') card_text($im, 17, $sx, $ly + 54, $muted, $mont, $d['signer_role']);
        $orgLine = $d['org'] . ($d['cnpj'] ? ' · CNPJ ' . $d['cnpj'] : '');
        $os = 15.0; while ($os > 10 && card_text_w($os, $mont, $orgLine) > $rw) $os -= 0.5;
        card_text($im, $os, $sx, $ly + 78, $muted, $mont, $orgLine);
    }
    return $im;
}

function card_png(array $d, string $face): string
{
    $im = card_render($d, $face);
    ob_start(); imagepng($im, null, 6); $b = (string)ob_get_clean(); imagedestroy($im);
    return $b;
}
function card_jpeg(array $d, string $face): string
{
    $im = card_render($d, $face);
    ob_start(); imagejpeg($im, null, 93); $b = (string)ob_get_clean(); imagedestroy($im);
    return $b;
}

/** PDF A4 com frente e verso em tamanho real (85,6 x 54 mm) e marcas de corte. Sem bibliotecas externas. */
function card_pdf(string $jpgFront, string $jpgBack): string
{
    $mm = 72 / 25.4;
    $cw = 85.6 * $mm; $ch = 54 * $mm; $pw = 595.28; $ph = 841.89;
    $x = ($pw - $cw) / 2;
    $yf = $ph - 120 - $ch; $yb = $yf - 62 - $ch;
    $enc = fn(string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'CP1252//TRANSLIT', $s));
    $f = fn(float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    $c = "q " . $f($cw) . " 0 0 " . $f($ch) . " " . $f($x) . " " . $f($yf) . " cm /Im1 Do Q\n";
    $c .= "q " . $f($cw) . " 0 0 " . $f($ch) . " " . $f($x) . " " . $f($yb) . " cm /Im2 Do Q\n0.4 w 0.5 G\n";
    foreach ([$yf, $yb] as $yy) foreach ([[$x, $yy], [$x + $cw, $yy], [$x, $yy + $ch], [$x + $cw, $yy + $ch]] as $i => [$cx, $cy]) {
        $dx = ($i % 2 === 0) ? -1 : 1; $dy = $i < 2 ? -1 : 1;
        $c .= $f($cx + $dx * 3) . " " . $f($cy) . " m " . $f($cx + $dx * 12) . " " . $f($cy) . " l S\n" . $f($cx) . " " . $f($cy + $dy * 3) . " m " . $f($cx) . " " . $f($cy + $dy * 12) . " l S\n";
    }
    $txt = function (string $s, float $size, float $tx, float $ty, string $gray) use ($enc, $f) { return "BT /F1 " . $f($size) . " Tf " . $gray . " g " . $f($tx) . " " . $f($ty) . " Td (" . $enc($s) . ") Tj ET\n"; };
    $c .= $txt('Carteirinha de capacitação', 14, 56, $ph - 56, '0.1');
    $c .= $txt('Imprima em 100% (sem ajustar à página), recorte nas marcas e, se quiser, plastifique frente e verso.', 8.5, 56, $ph - 74, '0.35');
    $c .= $txt('FRENTE', 8, $x, $yf + $ch + 16, '0.4');
    $c .= $txt('VERSO', 8, $x, $yb + $ch + 16, '0.4');
    $c .= $txt('Medidas: 85,6 x 54 mm (tamanho de cartão de crédito).', 8, 56, 60, '0.4');

    $objs = [];
    $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objs[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objs[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 5 0 R >> /XObject << /Im1 6 0 R /Im2 7 0 R >> >> /Contents 4 0 R >>";
    $objs[4] = "<< /Length " . strlen($c) . " >>\nstream\n" . $c . "endstream";
    $objs[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
    foreach ([6 => $jpgFront, 7 => $jpgBack] as $n => $jpg) {
        $objs[$n] = "<< /Type /XObject /Subtype /Image /Width " . CARD_W . " /Height " . CARD_H . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpg) . " >>\nstream\n" . $jpg . "\nendstream";
    }
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $off = [];
    foreach ($objs as $n => $o) { $off[$n] = strlen($pdf); $pdf .= "$n 0 obj\n$o\nendobj\n"; }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
    return $pdf . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}
