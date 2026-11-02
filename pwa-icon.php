<?php
// Ícone do app (PNG) desenhado na hora: logo do site, se houver; senão o losango dourado.
require __DIR__ . '/app/bootstrap.php';
$s = ((int)($_GET['s'] ?? 192)) >= 512 ? 512 : 192;
$im = imagecreatetruecolor($s, $s);
imagealphablending($im, true);
imagefill($im, 0, 0, imagecolorallocate($im, 7, 8, 12));
$logo = ($f = basename(setting('site_logo'))) !== '' && is_file(ROOT . '/uploads/' . $f) ? @imagecreatefrompng(ROOT . '/uploads/' . $f) : null;
if ($logo) {
    $w = imagesx($logo); $h = imagesy($logo); $k = min($s * 0.62 / $w, $s * 0.62 / $h);   // dentro da área segura do ícone "maskable"
    $nw = (int)round($w * $k); $nh = (int)round($h * $k);
    imagecopyresampled($im, $logo, (int)(($s - $nw) / 2), (int)(($s - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
} else {
    $c = $s / 2; $r = $s * 0.28;
    imagefilledpolygon($im, [$c, $c - $r, $c + $r, $c, $c, $c + $r, $c - $r, $c], imagecolorallocate($im, 212, 175, 55));
    $r2 = $r * 0.5;
    imagefilledpolygon($im, [$c, $c - $r2, $c + $r2, $c, $c, $c + $r2, $c - $r2, $c], imagecolorallocate($im, 10, 31, 68));
}
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
imagepng($im);
