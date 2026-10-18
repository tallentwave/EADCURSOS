<?php
declare(strict_types=1);

const EXAM_GRACE_SEC = 30; // tolerância de rede no fim do tempo

function module_exam(int $moduleId): ?array { return q_one('SELECT * FROM exams WHERE module_id = ?', [$moduleId]); }
function final_exam(int $courseId): ?array { return q_one('SELECT * FROM exams WHERE course_id = ? AND module_id IS NULL', [$courseId]); }
function exam_qcount(int $examId): int { return (int)q_val('SELECT COUNT(*) FROM exam_questions WHERE exam_id = ?', [$examId]); }
/** Prova só vale (trava/exige) se estiver ativa e tiver ao menos uma questão. */
function exam_ready(?array $e): bool { return $e && $e['active'] && exam_qcount((int)$e['id']) > 0; }

function passed_exam_set(int $uid): array
{
    return array_flip(array_column(q_all('SELECT DISTINCT exam_id FROM exam_attempts WHERE user_id = ? AND passed = 1', [$uid]), 'exam_id'));
}
function exam_passed(int $uid, int $examId): bool { return (bool)q_val('SELECT 1 FROM exam_attempts WHERE user_id = ? AND exam_id = ? AND passed = 1', [$uid, $examId]); }
function exam_best(int $uid, int $examId): ?float
{
    $v = q_val('SELECT MAX(score) FROM exam_attempts WHERE user_id = ? AND exam_id = ? AND finished_at IS NOT NULL', [$uid, $examId]);
    return $v === null ? null : (float)$v;
}
function exam_attempts_used(int $uid, int $examId): int { return (int)q_val('SELECT COUNT(*) FROM exam_attempts WHERE user_id = ? AND exam_id = ?', [$uid, $examId]); }

/** module_id => exam_id das provas obrigatórias e válidas do curso. */
function required_module_exams(int $courseId): array
{
    $out = [];
    foreach (q_all('SELECT e.id, e.module_id FROM exams e WHERE e.course_id = ? AND e.module_id IS NOT NULL AND e.required = 1 AND e.active = 1
                    AND EXISTS (SELECT 1 FROM exam_questions q WHERE q.exam_id = e.id)', [$courseId]) as $r) $out[(int)$r['module_id']] = (int)$r['id'];
    return $out;
}

function module_done(int $uid, int $moduleId): bool
{
    $counted = 'NOT (l.lesson_type = "live" AND l.live_required = 0)';
    $total = (int)q_val("SELECT COUNT(*) FROM lessons l WHERE l.module_id = ? AND $counted", [$moduleId]);
    $done = (int)q_val("SELECT COUNT(*) FROM progress p JOIN lessons l ON l.id = p.lesson_id WHERE p.user_id = ? AND l.module_id = ? AND p.completed = 1 AND $counted", [$uid, $moduleId]);
    return $done >= $total;
}

/** Mensagem de bloqueio da prova ('' = liberada). */
function exam_prereq(int $uid, array $e): string
{
    $u = current_user();
    if ($u && (int)$u['id'] === $uid && $u['role'] === 'admin') return '';
    if (!has_access($uid, (int)$e['course_id'])) return 'Você não tem acesso a este curso.';
    if ($e['module_id'] !== null) return module_done($uid, (int)$e['module_id']) ? '' : 'Conclua todas as aulas do módulo para liberar esta prova.';
    $p = course_progress($uid, (int)$e['course_id']);
    if ($p['total'] > 0 && $p['pct'] < 100) return 'Conclua todas as aulas do curso para liberar a avaliação final.';
    $passed = passed_exam_set($uid);
    foreach (required_module_exams((int)$e['course_id']) as $mid => $eid) {
        if (!isset($passed[$eid])) return 'Seja aprovado na prova do módulo "' . q_val('SELECT title FROM modules WHERE id = ?', [$mid]) . '" antes da avaliação final.';
    }
    return '';
}

/** Primeira prova obrigatória pendente cujo módulo já foi todo assistido (para redirecionar o aluno). */
function first_blocking_exam(int $uid, int $courseId): ?array
{
    $req = required_module_exams($courseId);
    if (!$req) return null;
    $passed = passed_exam_set($uid);
    foreach (q_all('SELECT id FROM modules WHERE course_id = ? ORDER BY position, id', [$courseId]) as $m) {
        $eid = $req[(int)$m['id']] ?? null;
        if ($eid && !isset($passed[$eid])) return module_done($uid, (int)$m['id']) ? q_one('SELECT * FROM exams WHERE id = ?', [$eid]) : null;
    }
    return null;
}

/** @return array{0:bool,1:string} */
function certificate_eligible(int $uid, int $courseId): array
{
    $p = course_progress($uid, $courseId);
    if ($p['total'] === 0 || $p['pct'] < 100) return [false, 'Conclua 100% das aulas.'];
    $passed = passed_exam_set($uid);
    foreach (required_module_exams($courseId) as $mid => $eid) {
        if (!isset($passed[$eid])) return [false, 'Seja aprovado na prova do módulo "' . q_val('SELECT title FROM modules WHERE id = ?', [$mid]) . '".'];
    }
    $f = final_exam($courseId);
    if (exam_ready($f) && !isset($passed[(int)$f['id']])) return [false, 'Seja aprovado na avaliação final.'];
    return [true, ''];
}

// ---------- Tentativas ----------
function attempt_deadline(array $e, array $a): ?int
{
    return (int)$e['time_limit_min'] > 0 ? strtotime($a['started_at']) + (int)$e['time_limit_min'] * 60 : null;
}

/** Cria a tentativa: sorteia/embaralha questões e alternativas e guarda a ordem no servidor. */
function exam_start(int $uid, array $e): int
{
    $qs = q_all('SELECT id FROM exam_questions WHERE exam_id = ? ORDER BY position, id', [$e['id']]);
    $ids = array_column($qs, 'id');
    if ((int)$e['shuffle'] || (int)$e['draw'] > 0) shuffle($ids);
    if ((int)$e['draw'] > 0) $ids = array_slice($ids, 0, (int)$e['draw']);
    if (!(int)$e['shuffle']) sort($ids);
    $layout = [];
    foreach ($ids as $qid) {
        $o = array_column(q_all('SELECT id FROM exam_options WHERE question_id = ? ORDER BY position, id', [$qid]), 'id');
        shuffle($o);
        $layout[] = ['q' => (int)$qid, 'o' => array_map('intval', $o)];
    }
    q('INSERT INTO exam_attempts (user_id, exam_id, started_at, layout) VALUES (?, ?, ?, ?)', [$uid, $e['id'], now(), json_encode($layout)]);
    return (int)db()->lastInsertId();
}

/**
 * Corrige e encerra a tentativa de forma atômica (só a primeira chamada vale).
 * Fora do prazo (+tolerância) a nota é 0.
 * @return array|null tentativa atualizada, ou null se já estava encerrada
 */
function exam_finish(array $a, array $e, array $answers): ?array
{
    $layout = json_decode($a['layout'], true) ?: [];
    $deadline = attempt_deadline($e, $a);
    $late = $deadline !== null && time() > $deadline + EXAM_GRACE_SEC;
    $clean = [];
    $correct = 0;
    $total = count($layout);
    if ($total > 0) {
        $in = implode(',', array_fill(0, $total, '?'));
        $right = [];
        foreach (q_all("SELECT question_id, id FROM exam_options WHERE is_correct = 1 AND question_id IN ($in)", array_column($layout, 'q')) as $r) $right[(int)$r['question_id']][(int)$r['id']] = true;
        foreach ($layout as $row) {
            $given = (int)($answers[$row['q']] ?? 0);
            if (!in_array($given, $row['o'], true)) continue; // alternativa fora da ordem sorteada é ignorada
            $clean[$row['q']] = $given;
            if (!$late && isset($right[$row['q']][$given])) $correct++;
        }
    }
    $score = $total > 0 ? round($correct * 100 / $total, 2) : 0.0;
    $passed = !$late && $score + 0.001 >= (float)$e['min_score'] ? 1 : 0;
    $n = q('UPDATE exam_attempts SET finished_at = ?, score = ?, passed = ?, timed_out = ?, answers = ? WHERE id = ? AND finished_at IS NULL',
        [now(), $score, $passed, $late ? 1 : 0, json_encode($clean), $a['id']])->rowCount();
    return $n === 1 ? q_one('SELECT * FROM exam_attempts WHERE id = ?', [$a['id']]) : null;
}

/** Linha da prova na página do curso. */
function exam_row_html(?array $e, bool $owned, int $uid): string
{
    if (!exam_ready($e)) return '';
    $passed = $owned && exam_passed($uid, (int)$e['id']);
    $best = $owned ? exam_best($uid, (int)$e['id']) : null;
    $label = $e['module_id'] === null ? 'Avaliação final' : 'Prova do módulo';
    $meta = 'nota mínima ' . (int)$e['min_score'] . '%' . ($e['module_id'] !== null && !$e['required'] ? ' · opcional' : '');
    if ($passed) $status = '<span class="tag-s tag-ok">Aprovado ' . rtrim(rtrim(number_format((float)$best, 2, ',', ''), '0'), ',') . '%</span>';
    elseif ($owned && $best !== null) $status = '<span class="tag-s tag-bad">Melhor nota ' . rtrim(rtrim(number_format($best, 2, ',', ''), '0'), ',') . '%</span>';
    else $status = '<span class="tag-s tag-warn">Pendente</span>';
    $inner = '<span class="dot">' . ($passed ? '&#10003;' : '&#128221;') . '</span><span class="t"><strong>' . $label . ':</strong> ' . e($e['title']) . ' <small style="color:var(--muted)">· ' . $meta . '</small></span>' . ($owned ? $status : '<span>&#128274;</span>');
    return $owned ? '<a class="lesson-item exam-row" href="' . e(url('prova.php?id=' . $e['id'])) . '">' . $inner . '</a>' : '<div class="lesson-item exam-row" style="opacity:.6">' . $inner . '</div>';
}
