<?php
require __DIR__ . '/_init.php';
$batchF = trim($_GET['lote'] ?? '');

if (($_GET['export'] ?? '') === '1') {
    $rows = q_all('SELECT v.code, c.title, v.batch, v.expires_at, v.redeemed_at, u.email FROM vouchers v JOIN courses c ON c.id = v.course_id LEFT JOIN users u ON u.id = v.redeemed_by'
        . ($batchF !== '' ? ' WHERE v.batch = ?' : '') . ' ORDER BY v.id', $batchF !== '' ? [$batchF] : []);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vouchers.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Código', 'Curso', 'Lote', 'Validade', 'Resgatado em', 'Resgatado por'], ';');
    $safe = fn($s) => preg_match('/^[=+\-@]/', (string)$s) ? "'" . $s : (string)$s; // evita injeção de fórmula no Excel
    foreach ($rows as $r) fputcsv($out, [$r['code'], $safe($r['title']), $safe($r['batch']), $r['expires_at'] ?? '', $r['redeemed_at'] ?? '', $r['email'] ?? ''], ';');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    if ($a === 'generate') {
        $cid = (int)($_POST['course_id'] ?? 0);
        $qty = max(1, min(500, (int)($_POST['qty'] ?? 1)));
        $batch = mb_substr(trim($_POST['batch'] ?? ''), 0, 80) ?: 'Lote ' . date('d/m/Y H:i');
        $exp = trim($_POST['expires'] ?? '') !== '' && strtotime($_POST['expires']) ? date('Y-m-d 23:59:59', strtotime($_POST['expires'])) : null;
        if (!course_by_id($cid)) flash('err', 'Selecione um curso.');
        else {
            $made = 0;
            $st = db()->prepare('INSERT IGNORE INTO vouchers (code, course_id, batch, expires_at, created_at) VALUES (?,?,?,?,?)');
            while ($made < $qty) { $st->execute([voucher_generate_code(), $cid, $batch, $exp, now()]); $made += $st->rowCount(); }
            flash('ok', "$qty voucher(s) gerado(s) no lote \"$batch\".");
            back('vouchers.php?lote=' . urlencode($batch));
        }
    } elseif ($a === 'delete') {
        q('DELETE FROM vouchers WHERE id = ? AND redeemed_by IS NULL', [(int)$_POST['id']]);
    }
    back('vouchers.php' . ($batchF !== '' ? '?lote=' . urlencode($batchF) : ''));
}
$courses = q_all('SELECT id, title FROM courses ORDER BY title');
$batches = q_all('SELECT batch, COUNT(*) n, SUM(redeemed_by IS NOT NULL) used FROM vouchers GROUP BY batch ORDER BY MAX(id) DESC LIMIT 50');
$rows = q_all('SELECT v.*, c.title, u.email FROM vouchers v JOIN courses c ON c.id = v.course_id LEFT JOIN users u ON u.id = v.redeemed_by'
    . ($batchF !== '' ? ' WHERE v.batch = ?' : '') . ' ORDER BY v.id DESC LIMIT 500', $batchF !== '' ? [$batchF] : []);
require ROOT . '/app/views/admin_header.php';
?>
<h1>Vouchers</h1>
<div class="hint" style="margin-bottom:14px">Vouchers são códigos pré-pagos que liberam um curso (ex.: vendas por fora, presentes, parcerias). O aluno resgata em <strong><?= e(url('resgatar.php')) ?></strong>. Cada código vale uma única vez.</div>
<form class="formbox" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="generate">
<div class="grid2"><div><label>Curso</label><select name="course_id" required><?php foreach ($courses as $c) echo '<option value="' . (int)$c['id'] . '">' . e($c['title']) . '</option>'; ?></select></div>
<div><label>Quantidade (1 a 500)</label><input type="number" name="qty" min="1" max="500" value="10" required></div>
<div><label>Nome do lote</label><input type="text" name="batch" maxlength="80" placeholder="Ex.: Parceria Empresa X"></div>
<div><label>Válido até (opcional)</label><input type="text" name="expires" placeholder="AAAA-MM-DD"></div></div>
<br><button class="btn">Gerar vouchers</button></form>
<div class="toolbar"><strong>Lotes:</strong>
<a class="btn btn-ghost btn-sm" href="<?= e(url('admin/vouchers.php')) ?>">Todos</a>
<?php foreach ($batches as $b): ?><a class="btn <?= $b['batch'] === $batchF ? '' : 'btn-ghost' ?> btn-sm" href="<?= e(url('admin/vouchers.php?lote=' . urlencode($b['batch']))) ?>"><?= e($b['batch']) ?> (<?= (int)$b['used'] ?>/<?= (int)$b['n'] ?>)</a><?php endforeach; ?>
<a class="btn btn-blue btn-sm" href="<?= e(url('admin/vouchers.php?export=1' . ($batchF !== '' ? '&lote=' . urlencode($batchF) : ''))) ?>">Exportar CSV</a></div>
<div class="table-wrap"><table><tr><th>Código</th><th>Curso</th><th>Lote</th><th>Validade</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $v): $exp = $v['expires_at'] && strtotime($v['expires_at']) < time(); ?><tr>
<td><code style="color:var(--gold2);letter-spacing:1px"><?= e($v['code']) ?></code></td><td><?= e($v['title']) ?></td><td><?= e($v['batch']) ?></td>
<td><?= $v['expires_at'] ? e(date('d/m/Y', strtotime($v['expires_at']))) : '—' ?></td>
<td><?php if ($v['redeemed_by'] !== null): ?><span class="tag-s tag-warn">Usado</span> <small style="color:var(--muted)"><?= e($v['email'] ?? '') ?> · <?= e(date('d/m/Y', strtotime($v['redeemed_at']))) ?></small>
<?php else: ?><span class="tag-s tag-<?= $exp ? 'bad' : 'ok' ?>"><?= $exp ? 'Expirado' : 'Disponível' ?></span><?php endif; ?></td>
<td><?php if ($v['redeemed_by'] === null): ?><form class="inline" method="post" data-confirm="Excluir este voucher?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="btn btn-danger btn-sm">Excluir</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6">Nenhum voucher.</td></tr><?php endif; ?></table></div>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
