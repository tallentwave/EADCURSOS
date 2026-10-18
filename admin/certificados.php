<?php
require __DIR__ . '/_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'revoke') { q('UPDATE certificates SET status = "revoked", revoked_at = ? WHERE id = ?', [now(), $id]); flash('ok', 'Certificado revogado. A página pública passa a informar que ele é inválido.'); }
    if (($_POST['action'] ?? '') === 'restore') { q('UPDATE certificates SET status = "valid", revoked_at = NULL WHERE id = ?', [$id]); flash('ok', 'Certificado reativado.'); }
    back('certificados.php');
}
$qs = trim($_GET['q'] ?? '');
$rows = q_all('SELECT ce.*, u.email FROM certificates ce LEFT JOIN users u ON u.id = ce.user_id' . ($qs !== '' ? ' WHERE ce.code LIKE ? OR ce.holder_name LIKE ? OR u.email LIKE ?' : '') . ' ORDER BY ce.id DESC LIMIT 300',
    $qs !== '' ? ['%' . preg_replace('/[^A-Za-z0-9]/', '', $qs) . '%', "%$qs%", "%$qs%"] : []);
require ROOT . '/app/views/admin_header.php';
?>
<h1>Certificados emitidos</h1>
<form class="toolbar" method="get"><input type="text" name="q" value="<?= e($qs) ?>" placeholder="Buscar por código, nome ou e-mail" style="max-width:340px"><button class="btn btn-sm">Buscar</button></form>
<div class="table-wrap"><table><tr><th>Código</th><th>Titular</th><th>CPF</th><th>Curso</th><th>Emissão</th><th>Página pública</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr>
<td><code><?= e(cert_code_display($r['code'])) ?></code></td><td><?= e($r['holder_name']) ?><br><small style="color:var(--muted)"><?= e($r['email'] ?? '') ?></small></td>
<td><?= e($r['holder_cpf_masked'] ?? '—') ?></td><td><?= e($r['course_title']) ?></td><td><?= e(date('d/m/Y', strtotime($r['issued_at']))) ?></td>
<td><a href="<?= e(cert_public_url($r['code'])) ?>" target="_blank">abrir</a> <small style="color:var(--muted)">· <?= $r['show_details'] ? 'completa' : 'reservada' ?></small></td>
<td><span class="tag-s tag-<?= $r['status'] === 'valid' ? 'ok' : 'bad' ?>"><?= $r['status'] === 'valid' ? 'Válido' : 'Revogado' ?></span></td>
<td><form class="inline" method="post" data-confirm="Confirmar?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<?php if ($r['status'] === 'valid'): ?><button class="btn btn-danger btn-sm" name="action" value="revoke">Revogar</button><?php else: ?><button class="btn btn-ghost btn-sm" name="action" value="restore">Reativar</button><?php endif; ?></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8">Nenhum certificado.</td></tr><?php endif; ?></table></div>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
