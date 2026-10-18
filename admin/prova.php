<?php
require __DIR__ . '/_init.php';
$cid = (int)($_GET['curso'] ?? $_POST['curso'] ?? 0);
$mid = (int)($_GET['modulo'] ?? $_POST['modulo'] ?? 0); // 0 = avaliação final
$c = course_by_id($cid);
if (!$c) back('cursos.php');
$module = $mid ? q_one('SELECT * FROM modules WHERE id = ? AND course_id = ?', [$mid, $cid]) : null;
if ($mid && !$module) back('conteudo.php?curso=' . $cid);
$e = $mid ? module_exam($mid) : final_exam($cid);
$here = 'prova.php?curso=' . $cid . '&modulo=' . $mid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    if ($a === 'save_exam') {
        $f = [
            mb_substr(trim($_POST['title'] ?? '') ?: ($module ? 'Prova — ' . $module['title'] : 'Avaliação final'), 0, 190),
            max(1, min(100, (int)($_POST['min_score'] ?? 70))),
            max(0, min(50, (int)($_POST['max_attempts'] ?? 3))),
            max(0, min(600, (int)($_POST['time_limit_min'] ?? 0))),
            max(0, (int)($_POST['draw'] ?? 0)),
            $module && !isset($_POST['required']) ? 0 : 1,
            isset($_POST['shuffle']) ? 1 : 0,
            isset($_POST['active']) ? 1 : 0,
        ];
        if ($e) q('UPDATE exams SET title=?, min_score=?, max_attempts=?, time_limit_min=?, draw=?, required=?, shuffle=?, active=? WHERE id=?', [...$f, $e['id']]);
        else q('INSERT INTO exams (title, min_score, max_attempts, time_limit_min, draw, required, shuffle, active, course_id, module_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [...$f, $cid, $mid ?: null, now()]);
        flash('ok', 'Configurações da prova salvas.');
    } elseif ($e && $a === 'save_q') {
        $body = trim($_POST['body'] ?? '');
        $opts = array_values(array_filter(array_map('trim', (array)($_POST['opt'] ?? [])), fn($o) => $o !== ''));
        $rawOpts = (array)($_POST['opt'] ?? []);
        $correctIdx = (int)($_POST['correct'] ?? -1);
        $correctText = isset($rawOpts[$correctIdx]) ? trim($rawOpts[$correctIdx]) : '';
        if ($body === '' || mb_strlen($body) > 3000) flash('err', 'Escreva o enunciado.');
        elseif (count($opts) < 2) flash('err', 'Informe pelo menos 2 alternativas.');
        elseif ($correctText === '') flash('err', 'Marque a alternativa correta (ela não pode estar vazia).');
        else {
            $qid = (int)($_POST['qid'] ?? 0);
            if ($qid && q_val('SELECT 1 FROM exam_questions WHERE id = ? AND exam_id = ?', [$qid, $e['id']])) {
                q('UPDATE exam_questions SET body = ? WHERE id = ?', [$body, $qid]);
                q('DELETE FROM exam_options WHERE question_id = ?', [$qid]);
            } else {
                q('INSERT INTO exam_questions (exam_id, body, position) VALUES (?,?,?)', [$e['id'], $body, (int)q_val('SELECT COALESCE(MAX(position),0)+1 FROM exam_questions WHERE exam_id = ?', [$e['id']])]);
                $qid = (int)db()->lastInsertId();
            }
            $pos = 0;
            foreach ($rawOpts as $i => $o) {
                $o = mb_substr(trim($o), 0, 500);
                if ($o === '') continue;
                q('INSERT INTO exam_options (question_id, body, is_correct, position) VALUES (?,?,?,?)', [$qid, $o, (int)$i === $correctIdx ? 1 : 0, ++$pos]);
            }
            flash('ok', 'Questão salva.');
        }
    } elseif ($e && $a === 'del_q') {
        q('DELETE FROM exam_questions WHERE id = ? AND exam_id = ?', [(int)$_POST['qid'], $e['id']]);
    } elseif ($e && $a === 'reset') {
        q('DELETE FROM exam_attempts WHERE exam_id = ? AND user_id = ?', [$e['id'], (int)$_POST['user_id']]);
        flash('ok', 'Tentativas do aluno zeradas.');
    } elseif ($e && $a === 'del_exam') {
        q('DELETE FROM exams WHERE id = ?', [$e['id']]);
        flash('ok', 'Prova excluída.');
        back('conteudo.php?curso=' . $cid);
    }
    back($here);
}

$questions = $e ? q_all('SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY position, id', [$e['id']]) : [];
$optsBy = [];
foreach ($questions as $q0) $optsBy[$q0['id']] = q_all('SELECT * FROM exam_options WHERE question_id = ? ORDER BY position, id', [$q0['id']]);
$edit = null;
if ($e && isset($_GET['q'])) foreach ($questions as $q0) if ((int)$q0['id'] === (int)$_GET['q']) $edit = $q0;
$attempts = $e ? q_all('SELECT a.*, u.name, u.email FROM exam_attempts a JOIN users u ON u.id = a.user_id WHERE a.exam_id = ? ORDER BY a.id DESC LIMIT 100', [$e['id']]) : [];
$stat = $e ? q_one('SELECT COUNT(*) n, SUM(passed) ok, AVG(score) avg FROM exam_attempts WHERE exam_id = ? AND finished_at IS NOT NULL', [$e['id']]) : null;
$d = $e ?: ['title' => $module ? 'Prova — ' . $module['title'] : 'Avaliação final', 'min_score' => 70, 'max_attempts' => 3, 'time_limit_min' => 0, 'draw' => 0, 'required' => 1, 'shuffle' => 1, 'active' => 1];
require ROOT . '/app/views/admin_header.php';
?>
<h1><?= $module ? 'Prova do módulo' : 'Avaliação final' ?></h1>
<div class="toolbar"><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/conteudo.php?curso=' . $cid)) ?>">&laquo; Conteúdo de <?= e($c['title']) ?></a>
<?php if ($module): ?><span class="hint">Módulo: <?= e($module['title']) ?></span><?php endif; ?></div>
<div class="hint" style="margin-bottom:14px"><?= $module
    ? 'O aluno faz esta prova depois de concluir as aulas do módulo. Se for obrigatória, o módulo seguinte só abre após a aprovação.'
    : 'A avaliação final libera o certificado: o aluno precisa concluir todas as aulas, passar nas provas obrigatórias dos módulos e ser aprovado aqui.' ?></div>

<form class="formbox" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_exam"><input type="hidden" name="curso" value="<?= $cid ?>"><input type="hidden" name="modulo" value="<?= $mid ?>">
<label>Título</label><input type="text" name="title" value="<?= e($d['title']) ?>" maxlength="190">
<div class="grid2">
<div><label>Nota mínima para aprovação (%)</label><input type="number" name="min_score" min="1" max="100" value="<?= (int)$d['min_score'] ?>"></div>
<div><label>Tentativas permitidas (0 = ilimitadas)</label><input type="number" name="max_attempts" min="0" max="50" value="<?= (int)$d['max_attempts'] ?>"></div>
<div><label>Tempo limite em minutos (0 = sem limite)</label><input type="number" name="time_limit_min" min="0" max="600" value="<?= (int)$d['time_limit_min'] ?>"></div>
<div><label>Questões sorteadas por tentativa (0 = todas)</label><input type="number" name="draw" min="0" value="<?= (int)$d['draw'] ?>"><div class="hint">Com banco maior que o sorteio, cada aluno recebe uma prova diferente.</div></div></div>
<?php if ($module): ?><label class="check"><input type="checkbox" name="required" <?= $d['required'] ? 'checked' : '' ?>> Obrigatória (bloqueia o próximo módulo e é exigida para o certificado)</label><?php endif; ?>
<label class="check"><input type="checkbox" name="shuffle" <?= $d['shuffle'] ? 'checked' : '' ?>> Embaralhar ordem das questões (as alternativas sempre são embaralhadas)</label>
<label class="check"><input type="checkbox" name="active" <?= $d['active'] ? 'checked' : '' ?>> Prova ativa</label>
<br><button class="btn"><?= $e ? 'Salvar configurações' : 'Criar prova' ?></button></form>

<?php if ($e): ?>
<h2 style="margin:8px 0 12px;color:var(--gold)">Questões (<?= count($questions) ?>)</h2>
<?php if (!$questions): ?><div class="alert alert-info" style="margin:0 0 14px">A prova só passa a valer (travar módulos / exigir certificado) depois de ter ao menos uma questão.</div><?php endif; ?>
<?php foreach ($questions as $i => $q0): ?>
<div class="formbox"><p style="margin-top:14px"><strong><?= $i + 1 ?>.</strong> <span style="white-space:pre-wrap"><?= e($q0['body']) ?></span></p>
<ul style="margin:8px 0 12px 22px"><?php foreach ($optsBy[$q0['id']] as $o): ?><li style="<?= $o['is_correct'] ? 'color:var(--ok);font-weight:600' : 'color:var(--muted)' ?>"><?= e($o['body']) ?><?= $o['is_correct'] ? ' ✔' : '' ?></li><?php endforeach; ?></ul>
<a class="btn btn-ghost btn-sm" href="<?= e(url('admin/' . $here . '&q=' . $q0['id'])) ?>#form-q">Editar</a>
<form class="inline" method="post" data-confirm="Excluir esta questão?"><?= csrf_field() ?><input type="hidden" name="action" value="del_q"><input type="hidden" name="curso" value="<?= $cid ?>"><input type="hidden" name="modulo" value="<?= $mid ?>"><input type="hidden" name="qid" value="<?= (int)$q0['id'] ?>"><button class="btn btn-danger btn-sm">Excluir</button></form></div>
<?php endforeach; ?>

<form class="formbox" method="post" id="form-q"><?= csrf_field() ?><input type="hidden" name="action" value="save_q"><input type="hidden" name="curso" value="<?= $cid ?>"><input type="hidden" name="modulo" value="<?= $mid ?>"><input type="hidden" name="qid" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
<h2 style="margin:14px 0 0"><?= $edit ? 'Editar questão' : 'Nova questão' ?></h2>
<label>Enunciado</label><textarea name="body" required maxlength="3000"><?= e($edit['body'] ?? '') ?></textarea>
<label>Alternativas (2 a 6) — marque a correta</label>
<?php $eo = $edit ? $optsBy[$edit['id']] : []; for ($i = 0; $i < 6; $i++): $o = $eo[$i] ?? null; ?>
<div style="display:flex;gap:10px;align-items:center;margin-bottom:8px"><input type="radio" name="correct" value="<?= $i ?>" style="width:auto" <?= ($o && $o['is_correct']) || (!$o && !$edit && $i === 0) ? 'checked' : '' ?>>
<input type="text" name="opt[]" maxlength="500" placeholder="Alternativa <?= $i + 1 ?><?= $i >= 2 ? ' (opcional)' : '' ?>" value="<?= e($o['body'] ?? '') ?>"></div>
<?php endfor; ?>
<br><button class="btn"><?= $edit ? 'Salvar questão' : 'Adicionar questão' ?></button><?php if ($edit): ?> <a class="btn btn-ghost" href="<?= e(url('admin/' . $here)) ?>">Cancelar</a><?php endif; ?></form>

<h2 style="margin:8px 0 12px;color:var(--gold)">Resultados</h2>
<?php if ($stat && $stat['n']): ?><p class="hint" style="margin-bottom:10px"><?= (int)$stat['n'] ?> tentativa(s) · <?= (int)$stat['ok'] ?> aprovada(s) · média <?= round((float)$stat['avg'], 1) ?>%</p><?php endif; ?>
<div class="table-wrap"><table><tr><th>Aluno</th><th>Data</th><th>Nota</th><th>Resultado</th><th></th></tr>
<?php foreach ($attempts as $at): ?><tr><td><?= e($at['name']) ?><br><small style="color:var(--muted)"><?= e($at['email']) ?></small></td>
<td><?= e(date('d/m/Y H:i', strtotime($at['started_at']))) ?></td><td><?= $at['score'] === null ? '—' : rtrim(rtrim(number_format((float)$at['score'], 2, ',', ''), '0'), ',') . '%' ?></td>
<td><?php if ($at['finished_at'] === null): ?><span class="tag-s tag-warn">Em andamento</span><?php else: ?><span class="tag-s tag-<?= $at['passed'] ? 'ok' : 'bad' ?>"><?= $at['passed'] ? 'Aprovado' : ($at['timed_out'] ? 'Tempo esgotado' : 'Reprovado') ?></span><?php endif; ?></td>
<td><form class="inline" method="post" data-confirm="Zerar TODAS as tentativas deste aluno nesta prova?"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="curso" value="<?= $cid ?>"><input type="hidden" name="modulo" value="<?= $mid ?>"><input type="hidden" name="user_id" value="<?= (int)$at['user_id'] ?>"><button class="btn btn-ghost btn-sm">Zerar tentativas</button></form></td></tr>
<?php endforeach; if (!$attempts): ?><tr><td colspan="5">Nenhuma tentativa ainda.</td></tr><?php endif; ?></table></div>
<form method="post" data-confirm="Excluir a prova, as questões e todos os resultados?" style="margin-top:20px"><?= csrf_field() ?><input type="hidden" name="action" value="del_exam"><input type="hidden" name="curso" value="<?= $cid ?>"><input type="hidden" name="modulo" value="<?= $mid ?>"><button class="btn btn-danger btn-sm">Excluir prova</button></form>
<?php endif; ?>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
