<?php
require __DIR__ . '/_init.php';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$l = q_one('SELECT l.*, m.course_id FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = ?', [$id]);
if (!$l) back('cursos.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $v = trim($_POST['video_url'] ?? '');
        [$sf, $serr] = lesson_schedule_fields($_POST);
        if ($v !== '' && !video_embed($v)) flash('err', 'Link de vídeo inválido: use YouTube ou Vimeo.');
        elseif ($serr !== '') flash('err', $serr);
        else {
            q('UPDATE lessons SET title=?, description=?, video_url=?, duration_min=?, duration_sec=?, is_free=?, lesson_type=?, starts_at=?, live_url=?, live_required=?, release_at=? WHERE id=?',
                [trim($_POST['title']) ?: $l['title'], trim($_POST['description'] ?? ''), $v, max(0, (int)$_POST['duration_min']), max(0, (int)($_POST['duration_sec'] ?? 0)), isset($_POST['is_free']) ? 1 : 0,
                 $sf['lesson_type'], $sf['starts_at'], $sf['live_url'], $sf['live_required'], $sf['release_at'], $id]);
            flash('ok', 'Aula salva.');
        }
    } elseif ($a === 'reminder' && lesson_is_live($l)) {
        $last = $l['reminder_sent_at'] ? strtotime($l['reminder_sent_at']) : 0;
        if (!$l['starts_at'] || live_state($l) === 'ended') flash('err', 'Esta aula não tem data futura para lembrar.');
        elseif (time() - $last < 600) flash('err', 'Um lembrete foi enviado há poucos minutos. Aguarde 10 minutos para reenviar.');
        else {
            @set_time_limit(180);
            $course = course_by_id((int)$l['course_id']);
            $rows = q_all('SELECT u.name, u.email FROM enrollments e JOIN users u ON u.id = e.user_id WHERE e.course_id = ? AND u.status = "active" AND (e.expires_at IS NULL OR e.expires_at > ?) LIMIT 1000', [$l['course_id'], now()]);
            $ok = 0; $fail = 0;
            foreach ($rows as $r) {
                $body = "Olá, {$r['name']}!\n\nLembrete: aula ao vivo do curso \"{$course['title']}\".\n\n" . $l['title'] . "\nQuando: " . live_when($l['starts_at']) . "\n\nA sala abre 15 minutos antes. Entre pela plataforma:\n" . url('aula.php?id=' . $id) . "\n\nAté lá!";
                if (mail_send($r['email'], 'Lembrete: aula ao vivo — ' . $l['title'], $body)) $ok++; else $fail++;
            }
            q('UPDATE lessons SET reminder_sent_at = ? WHERE id = ?', [now(), $id]);
            flash($fail && !$ok ? 'err' : 'ok', "Lembrete enviado a $ok aluno(s)" . ($fail ? " ($fail falha(s) — confira o SMTP em Configurações)" : '') . '.');
        }
    } elseif ($a === 'upload') {
        $f = $_FILES['pdf'] ?? null;
        $title = trim($_POST['mtitle'] ?? '');
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 20 * 1024 * 1024) flash('err', 'Arquivo inválido (máx. 20MB).');
        elseif ((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) !== 'application/pdf' || file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') flash('err', 'Envie apenas arquivos PDF.');
        else {
            $stored = bin2hex(random_bytes(16)) . '.pdf';
            if (move_uploaded_file($f['tmp_name'], ROOT . '/storage/materials/' . $stored)) {
                q('INSERT INTO materials (lesson_id, title, stored_name, size) VALUES (?,?,?,?)', [$id, $title ?: pathinfo($f['name'], PATHINFO_FILENAME), $stored, $f['size']]);
                flash('ok', 'Material enviado.');
            } else flash('err', 'Falha ao salvar o arquivo.');
        }
    } elseif ($a === 'del_material') {
        $m = q_one('SELECT * FROM materials WHERE id = ? AND lesson_id = ?', [(int)$_POST['mid'], $id]);
        if ($m) { @unlink(ROOT . '/storage/materials/' . basename($m['stored_name'])); q('DELETE FROM materials WHERE id = ?', [$m['id']]); }
    }
    back('aula_edit.php?id=' . $id);
}
$mats = q_all('SELECT * FROM materials WHERE lesson_id = ?', [$id]);
require ROOT . '/app/views/admin_header.php';
?>
<h1>Editar aula</h1>
<a class="btn btn-ghost btn-sm" href="<?= e(url('admin/conteudo.php?curso=' . $l['course_id'])) ?>">&laquo; Voltar ao conteúdo</a><br><br>
<form class="formbox" method="post" data-lesson-form><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="save">
<label>Título</label><input type="text" name="title" value="<?= e($l['title']) ?>" required>
<label>Tipo da aula</label><select name="lesson_type" data-lesson-type><option value="video" <?= !lesson_is_live($l) ? 'selected' : '' ?>>Gravada (vídeo)</option><option value="live" <?= lesson_is_live($l) ? 'selected' : '' ?>>Ao vivo / agendada</option></select>
<div class="only-live" hidden>
  <div class="grid2"><div><label>Data e hora da aula (horário de Brasília)</label><input type="datetime-local" name="starts_at" value="<?= e(dt_local_value($l['starts_at'])) ?>"></div>
  <div><label>Link da sala (Zoom, Google Meet, Teams, YouTube ao vivo...)</label><input type="url" name="live_url" value="<?= e($l['live_url']) ?>" placeholder="https://meet.google.com/abc-defg-hij"></div></div>
  <div class="hint">O link só é mostrado ao aluno matriculado, 15 minutos antes do início até 1 hora depois do fim previsto (use "Minutos" abaixo para a duração). Ele não aparece no código da página.</div>
  <label class="check"><input type="checkbox" name="live_required" <?= $l['live_required'] ? 'checked' : '' ?>> Presença obrigatória: libera a próxima aula só para quem entrou na aula ao vivo (ou assistiu à gravação). Desmarcado = aula opcional, não trava o aluno.</label>
</div>
<div class="only-video"><label>Liberar esta aula em (opcional — conteúdo agendado)</label><input type="datetime-local" name="release_at" value="<?= e(dt_local_value($l['release_at'])) ?>"><div class="hint">Antes dessa data a aula aparece com cadeado e a data de liberação.</div></div>
<label><span class="only-video">Link do vídeo (YouTube ou Vimeo)</span><span class="only-live" hidden>Link da gravação (opcional — fica disponível depois que a aula termina)</span></label><input type="url" name="video_url" value="<?= e($l['video_url']) ?>">
<label>Descrição</label><textarea name="description"><?= e($l['description']) ?></textarea>
<div class="grid2"><div><label>Duração aproximada (min)</label><input type="number" name="duration_min" min="0" value="<?= (int)$l['duration_min'] ?>"></div>
<div><label>Duração exata (segundos) — usada para conferir que o aluno assistiu até o fim</label><input type="number" name="duration_sec" min="0" value="<?= (int)$l['duration_sec'] ?>"><div class="hint">Dica: abra a aula como administrador e dê play por alguns segundos — a duração exata é gravada automaticamente.</div></div></div>
<label class="check"><input type="checkbox" name="is_free" <?= $l['is_free'] ? 'checked' : '' ?>> Aula gratuita (visível sem comprar o curso)</label><br><button class="btn">Salvar</button></form>

<?php if (lesson_is_live($l)):
    $att = q_all('SELECT u.name, u.email, a.joined_at FROM live_attendance a JOIN users u ON u.id = a.user_id WHERE a.lesson_id = ? ORDER BY a.joined_at', [$id]); ?>
<div class="formbox"><h2 style="margin:14px 0 6px">Aula ao vivo</h2>
  <p class="hint">Situação: <strong><?= e(['unscheduled' => 'sem data', 'upcoming' => 'agendada', 'soon' => 'sala aberta (começa em breve)', 'live' => 'AO VIVO agora', 'late' => 'encerrando (sala ainda aberta)', 'ended' => 'encerrada'][live_state($l)]) ?></strong>
  <?= $l['starts_at'] ? ' · ' . e(live_when($l['starts_at'])) : '' ?><?= $l['reminder_sent_at'] ? ' · último lembrete: ' . e(date('d/m H:i', strtotime($l['reminder_sent_at']))) : '' ?></p>
  <form method="post" data-confirm="Enviar o lembrete por e-mail a todos os alunos matriculados neste curso?" style="margin:10px 0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="reminder"><button class="btn btn-blue btn-sm">&#9993; Enviar lembrete por e-mail aos alunos</button></form>
  <h3 style="margin:14px 0 6px;color:var(--gold)">Presença (<?= count($att) ?>)</h3>
  <?php if ($att): ?><div class="table-wrap"><table><tr><th>Aluno</th><th>E-mail</th><th>Entrou em</th></tr>
  <?php foreach ($att as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td><?= e(date('d/m/Y H:i', strtotime($r['joined_at']))) ?></td></tr><?php endforeach; ?></table></div>
  <?php else: ?><p class="hint">Ninguém entrou ainda.</p><?php endif; ?></div>
<?php endif; ?>
<div class="formbox"><h2 style="margin:14px 0 6px">Materiais (PDF)</h2>
<?php foreach ($mats as $m): ?><div class="mat">&#128196; <?= e($m['title']) ?> <small style="color:var(--muted)"><?= round($m['size'] / 1024) ?> KB</small>
<form class="inline" method="post" data-confirm="Excluir material?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="del_material"><input type="hidden" name="mid" value="<?= (int)$m['id'] ?>"><button class="btn btn-danger btn-sm">Excluir</button></form></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="upload">
<div class="grid2"><div><label>Nome do material</label><input type="text" name="mtitle" placeholder="Ex.: Apostila da aula"></div><div><label>Arquivo PDF (até 20MB)</label><input type="file" name="pdf" accept="application/pdf" required></div></div><br><button class="btn btn-sm">Enviar PDF</button></form></div>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
