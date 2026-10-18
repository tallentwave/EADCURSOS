<?php
declare(strict_types=1);

/** Fração mínima do vídeo que o aluno precisa assistir para liberar a próxima aula. */
const WATCH_MIN_RATIO = 0.9;

function lesson_has_video(array $l): bool { return video_embed((string)($l['video_url'] ?? '')) !== null; }

/** Duração oficial: segundos exatos > minutos informados pelo admin. 0 = desconhecida. */
function lesson_duration(array $l): float
{
    if ((int)($l['duration_sec'] ?? 0) > 0) return (float)$l['duration_sec'];
    return (float)((int)($l['duration_min'] ?? 0) * 60);
}

/** Informações do player (provedor/ID) extraídas do link; nunca vão no HTML da aula. */
function video_source(string $url): ?array
{
    $url = trim($url);
    if (preg_match('#(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) return ['provider' => 'youtube', 'id' => $m[1], 'h' => ''];
    if (preg_match('#vimeo\.com/(?:video/)?(\d+)(?:/([a-f0-9]+))?#', $url, $m)) return ['provider' => 'vimeo', 'id' => $m[1], 'h' => $m[2] ?? ''];
    return null;
}

/**
 * Mapa aula => liberada? para um aluno. Uma aula com vídeo só libera a seguinte depois de concluída
 * (concluir = ter assistido o mínimo, registrado pelo servidor). Admin vê tudo liberado.
 */
function open_map(int $uid, int $courseId): array
{
    $u = current_user();
    $isAdmin = $u && (int)$u['id'] === $uid && $u['role'] === 'admin';
    $done = array_flip(array_column(q_all('SELECT lesson_id FROM progress WHERE user_id = ? AND completed = 1', [$uid]), 'lesson_id'));
    $reqExam = required_module_exams($courseId); // module_id => exam_id
    $passed = $reqExam ? passed_exam_set($uid) : [];
    $map = []; $prev = null; $gate = false;
    foreach (course_lessons($courseId) as $l) {
        $id = (int)$l['id'];
        // prova obrigatória do módulo anterior ainda não aprovada trava os módulos seguintes
        if ($prev && (int)$l['module_id'] !== (int)$prev['module_id'] && isset($reqExam[(int)$prev['module_id']]) && !isset($passed[$reqExam[(int)$prev['module_id']]])) $gate = true;
        $map[$id] = $isAdmin || (!$gate && ($prev === null || isset($done[$prev['id']]) || (!lesson_has_video($prev) && $map[(int)$prev['id']])));
        $prev = $l;
    }
    return $map;
}

/**
 * Registra batimento do player. Só avança o progresso com tempo REAL decorrido:
 * acelerar, pular ou reenviar pings não gera crédito além de ~1,05x do relógio do servidor.
 * @return array{progress:float,dur:float,need:float,pct:int,done:bool}
 */
function watch_ping(int $uid, array $lesson, float $pos, bool $playing, float $clientDur): array
{
    $dur = lesson_duration($lesson);
    if ($dur <= 0 && $clientDur >= 5 && $clientDur <= 86400) $dur = round($clientDur); // duração não cadastrada: usa a do player
    $need = $dur * WATCH_MIN_RATIO;
    $now = microtime(true);
    $lid = (int)$lesson['id'];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT IGNORE INTO lesson_watch (user_id, lesson_id, progress_sec, last_ping) VALUES (?, ?, 0, ?)', [$uid, $lid, $now]);
        $row = q_one('SELECT * FROM lesson_watch WHERE user_id = ? AND lesson_id = ? FOR UPDATE', [$uid, $lid]);
        $progress = (float)$row['progress_sec'];
        $dt = $now - (float)$row['last_ping'];
        if ($playing && $dt > 0 && $dt <= 12 && $pos > $progress && $pos <= 86400) {
            $progress += min($pos - $progress, $dt * 1.05);
        }
        if ($dur > 0) $progress = min($progress, $dur);
        q('UPDATE lesson_watch SET progress_sec = ?, last_ping = ? WHERE user_id = ? AND lesson_id = ?', [$progress, $now, $uid, $lid]);

        $done = $need > 0 && $progress + 0.001 >= $need;
        if ($done) {
            q('INSERT INTO progress (user_id, lesson_id, completed, updated_at) VALUES (?, ?, 1, ?)
               ON DUPLICATE KEY UPDATE completed = 1, updated_at = VALUES(updated_at)', [$uid, $lid, now()]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['progress' => $progress, 'dur' => $dur, 'need' => $need, 'pct' => $need > 0 ? (int)min(100, floor($progress * 100 / $need)) : 0, 'done' => $done];
}

/** Carrega a aula e valida acesso + sequência. Retorna [lesson, bypass] ou encerra com JSON de erro. */
function watch_guard(array $user, int $lessonId): array
{
    $lesson = q_one('SELECT l.*, m.course_id FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = ?', [$lessonId]);
    if (!$lesson) json_fail(404, 'Aula não encontrada.');
    $uid = (int)$user['id'];
    $cid = (int)$lesson['course_id'];
    if ($user['role'] === 'admin') return [$lesson, 'admin'];
    $course = course_by_id($cid);
    if (!$course || $course['status'] !== 'published') json_fail(404, 'Curso indisponível.');
    if (has_access($uid, $cid)) {
        if (!(open_map($uid, $cid)[$lessonId] ?? false)) json_fail(403, 'Conclua a aula anterior para liberar esta.');
        return [$lesson, ''];
    }
    if ($lesson['is_free']) return [$lesson, 'free'];
    json_fail(403, 'Sem acesso a esta aula.');
}
function json_fail(int $code, string $msg): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => $msg]);
    exit;
}
