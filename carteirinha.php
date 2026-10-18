<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$cid = (int)($_GET['curso'] ?? $_POST['curso'] ?? 0);
$c = course_by_id($cid);
if (!$c || !has_access($uid, $cid)) { http_response_code(403); exit('Sem acesso.'); }
$cert = card_cert($uid, $cid);
if (!$cert) {
    flash('info', 'A carteirinha fica disponível depois que você emitir o certificado deste curso.');
    redirect('curso.php?id=' . $cid);
}
$self = 'carteirinha.php?curso=' . $cid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    if ($a === 'photo') {
        if (empty($_POST['consent'])) flash('err', 'Para usar sua foto na carteirinha é preciso autorizar o uso da imagem.');
        else {
            try {
                $name = save_user_photo($_FILES['photo'] ?? [], $uid);
                $old = q_val('SELECT photo FROM users WHERE id = ?', [$uid]);
                q('UPDATE users SET photo = ?, photo_consent_at = ? WHERE id = ?', [$name, now(), $uid]);
                if ($old && ($p = user_photo_path($old))) @unlink($p);
                flash('ok', 'Foto salva. Confira o resultado abaixo.');
            } catch (RuntimeException $e) { flash('err', $e->getMessage()); }
        }
    } elseif ($a === 'remove_photo') { delete_user_photo($uid); flash('ok', 'Foto removida.'); }
    redirect($self);
}

$me = q_one('SELECT photo, photo_consent_at FROM users WHERE id = ?', [$uid]);
$hasPhoto = user_photo_bytes($me['photo']) !== null;
$v = substr(md5((string)$me['photo'] . ($cert['id']) . setting('cert_theme') . setting('cert_logo') . setting('cert_signature') . setting('cert_signer_name')), 0, 8);
$title = 'Carteirinha';
require ROOT . '/app/views/header.php';
?>
<div class="container" style="max-width:980px">
  <small style="color:var(--muted)"><a href="<?= e(url('curso.php?id=' . $cid)) ?>">&laquo; <?= e($c['title']) ?></a></small>
  <h1 style="color:var(--gold);margin:6px 0 4px">&#129706; Carteirinha de capacitação</h1>
  <p class="hint">Versão portátil do seu certificado, em tamanho de cartão de crédito. O QR Code leva à página oficial de validação.</p>

  <?php if (!$hasPhoto): ?>
  <div class="panel"><h3>1. Envie sua foto 3x4</h3>
    <p class="hint">Rosto centralizado, fundo claro e sem óculos escuros ou boné. Pode tirar uma selfie agora. A foto é recortada em 3x4 automaticamente.</p>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="photo"><input type="hidden" name="curso" value="<?= $cid ?>">
      <label>Foto (JPG, PNG ou WEBP, até 10MB)</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
      <label class="check"><input type="checkbox" name="consent" value="1" required> <span>Autorizo o uso da minha foto exclusivamente na minha carteirinha. Ela fica privada e <strong>nunca</strong> aparece na página pública de validação.</span></label>
      <br><button class="btn">Salvar foto e gerar carteirinha</button></form></div>
  <?php else: ?>
  <div class="card-prev">
    <figure><img src="<?= e(url('carteirinha_img.php?curso=' . $cid . '&face=front&v=' . $v)) ?>" alt="Frente da carteirinha"><figcaption>Frente</figcaption></figure>
    <figure><img src="<?= e(url('carteirinha_img.php?curso=' . $cid . '&face=back&v=' . $v)) ?>" alt="Verso da carteirinha"><figcaption>Verso</figcaption></figure>
  </div>
  <div class="toolbar" style="margin-top:18px">
    <a class="btn" href="<?= e(url('carteirinha_pdf.php?curso=' . $cid)) ?>">&#128196; Baixar PDF para imprimir</a>
    <a class="btn btn-blue" href="<?= e(url('carteirinha_img.php?curso=' . $cid . '&face=front&dl=1')) ?>">Salvar frente (PNG)</a>
    <a class="btn btn-blue" href="<?= e(url('carteirinha_img.php?curso=' . $cid . '&face=back&dl=1')) ?>">Salvar verso (PNG)</a>
    <a class="btn btn-ghost" href="<?= e(url('certificado.php?curso=' . $cid)) ?>">Ver certificado</a>
  </div>
  <p class="hint">No celular, salve as imagens e guarde na galeria ou na carteira digital. Impressa, ela fica do tamanho exato de um cartão (85,6 × 54 mm).</p>
  <div class="panel"><h3>Foto</h3>
    <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap">
      <img src="<?= e(url('foto.php?v=' . $v)) ?>" alt="Sua foto" style="width:96px;border-radius:8px;border:1px solid var(--line)">
      <div style="flex:1;min-width:240px"><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="photo"><input type="hidden" name="curso" value="<?= $cid ?>">
        <label style="margin-top:0">Trocar foto</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
        <label class="check"><input type="checkbox" name="consent" value="1" required> <span>Autorizo o uso da foto na minha carteirinha.</span></label><br><button class="btn btn-sm">Trocar</button></form>
        <form method="post" data-confirm="Remover sua foto? A carteirinha ficará sem foto até você enviar outra." style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="remove_photo"><input type="hidden" name="curso" value="<?= $cid ?>"><button class="btn btn-danger btn-sm">Remover foto</button></form></div>
    </div></div>
  <?php endif; ?>
</div>
<?php require ROOT . '/app/views/footer.php'; ?>
