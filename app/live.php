<?php
declare(strict_types=1);

// Aulas ao vivo / agendadas. O link da sala NUNCA vai no HTML: o aluno passa por ao-vivo.php,
// que confere matrícula e horário, registra a presença e só então redireciona.
const LIVE_EARLY_SEC = 900;    // sala abre 15 min antes do início
const LIVE_GRACE_SEC = 3600;   // e continua aberta até 1 h depois do fim previsto
const LIVE_DEFAULT_MIN = 90;   // duração assumida quando o admin não informa

function lesson_is_live(array $l): bool { return ($l['lesson_type'] ?? 'video') === 'live'; }

/** Instantes (epoch) importantes da aula ao vivo, ou null se não tiver data. */
function live_times(array $l): ?array
{
    if (empty($l['starts_at'])) return null;
    $start = strtotime((string)$l['starts_at']);
    if (!$start) return null;
    $dur = ((int)($l['duration_min'] ?? 0) ?: LIVE_DEFAULT_MIN) * 60;
    return ['open' => $start - LIVE_EARLY_SEC, 'start' => $start, 'end' => $start + $dur, 'close' => $start + $dur + LIVE_GRACE_SEC];
}

/** unscheduled | upcoming | soon (sala já aberta, antes do início) | live | late (passou do fim, ainda aberta) | ended */
function live_state(array $l, ?int $now = null): string
{
    $t = live_times($l);
    if (!$t) return 'unscheduled';
    $now ??= time();
    if ($now < $t['open']) return 'upcoming';
    if ($now < $t['start']) return 'soon';
    if ($now < $t['end']) return 'live';
    if ($now < $t['close']) return 'late';
    return 'ended';
}
function live_can_join(array $l): bool { return !empty($l['live_url']) && in_array(live_state($l), ['soon', 'live', 'late'], true); }
/** A gravação (video_url) só vale depois do fim previsto da transmissão. */
function live_replay_open(array $l): bool { $t = live_times($l); return !$t || time() >= $t['end']; }

/** Esta aula precisa ser concluída para liberar a seguinte? */
function lesson_gates(array $l): bool
{
    if (lesson_is_live($l)) return (bool)($l['live_required'] ?? 0);
    return lesson_has_video($l);
}
/** Aulas ao vivo opcionais não entram na conta de "100% do curso". */
function lesson_counts_for_completion(array $l): bool { return !lesson_is_live($l) || (bool)($l['live_required'] ?? 0); }

/** Data/hora em português: "sábado, 25 de outubro de 2026 às 19h30". */
function live_when(string $dt): string
{
    static $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    $t = strtotime($dt);
    return $dias[(int)date('w', $t)] . ', ' . date_long($dt) . ' às ' . date('H\hi', $t);
}
function live_short(string $dt): string { return date('d/m \à\s H:i', strtotime($dt)); }

/** Aceita só http(s) — evita javascript:, data: etc. no redirecionamento. */
function safe_live_url(string $u): ?string
{
    $u = trim($u);
    if ($u === '' || strlen($u) > 500 || preg_match('/[\x00-\x20\x7f]/', $u)) return null;
    $p = parse_url($u);
    return $p && in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) && !empty($p['host']) && filter_var($u, FILTER_VALIDATE_URL) ? $u : null;
}
/** Valor de <input type="datetime-local"> => 'Y-m-d H:i:00' (horário de Brasília) ou null. */
function parse_local_dt(string $v): ?string
{
    $v = trim($v);
    if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $v)) return null;
    $t = strtotime(str_replace('T', ' ', substr($v, 0, 16)) . ':00');
    return $t ? date('Y-m-d H:i:00', $t) : null;
}
function dt_local_value(?string $dt): string { return $dt ? date('Y-m-d\TH:i', strtotime($dt)) : ''; }

function live_mark_attended(int $uid, int $lessonId): void
{
    q('INSERT IGNORE INTO live_attendance (user_id, lesson_id, joined_at) VALUES (?, ?, ?)', [$uid, $lessonId, now()]);
    q('INSERT INTO progress (user_id, lesson_id, completed, updated_at) VALUES (?, ?, 1, ?)
       ON DUPLICATE KEY UPDATE completed = 1, updated_at = VALUES(updated_at)', [$uid, $lessonId, now()]);
}

/** Próximas aulas ao vivo dos cursos que o aluno tem (admin vê todas). */
function upcoming_lives(int $uid, bool $isAdmin, int $limit = 8): array
{
    $rows = q_all('SELECT l.*, c.title AS course_title, c.id AS course_id FROM lessons l
                   JOIN modules m ON m.id = l.module_id JOIN courses c ON c.id = m.course_id
                   WHERE l.lesson_type = "live" AND l.starts_at IS NOT NULL AND c.status = "published"
                     AND l.starts_at > ? ORDER BY l.starts_at LIMIT 80', [date('Y-m-d H:i:s', time() - 6 * 3600)]);
    $out = [];
    foreach ($rows as $r) {
        if (live_state($r) === 'ended') continue;
        if (!$isAdmin && !has_access($uid, (int)$r['course_id'])) continue;
        $out[] = $r;
        if (count($out) >= $limit) break;
    }
    return $out;
}

/** Arquivo .ics (calendário) com lembrete 15 min antes. */
function live_ics(array $l, string $courseTitle, string $pageUrl): string
{
    $t = live_times($l);
    $esc = fn(string $s) => str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\;", "\\,", "\\n", "\\n"], $s);
    $fold = function (string $line): string {
        $out = ''; while (strlen($line) > 74) { $out .= substr($line, 0, 74) . "\r\n "; $line = substr($line, 74); } return $out . $line;
    };
    $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . $esc(site_name()) . '//Aulas//PT', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
        'UID:live-' . (int)$l['id'] . '@' . preg_replace('/[^A-Za-z0-9.\-]/', '', parse_url(BASE_URL, PHP_URL_HOST) ?: 'ead'),
        'DTSTAMP:' . gmdate('Ymd\THis\Z'), 'DTSTART:' . gmdate('Ymd\THis\Z', $t['start']), 'DTEND:' . gmdate('Ymd\THis\Z', $t['end']),
        'SUMMARY:' . $esc('Aula ao vivo: ' . $l['title']), 'DESCRIPTION:' . $esc($courseTitle . ' — acesse a plataforma para entrar na aula: ' . $pageUrl), 'URL:' . $pageUrl,
        'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:' . $esc('A aula ao vivo começa em 15 minutos'), 'TRIGGER:-PT15M', 'END:VALARM', 'END:VEVENT', 'END:VCALENDAR'];
    return implode("\r\n", array_map($fold, $lines)) . "\r\n";
}

/**
 * Lê e valida os campos de tipo/agendamento vindos dos formulários do admin.
 * @return array{0:array,1:string} [campos, erro]
 */
function lesson_schedule_fields(array $post): array
{
    $type = ($post['lesson_type'] ?? 'video') === 'live' ? 'live' : 'video';
    $f = ['lesson_type' => $type, 'starts_at' => null, 'live_url' => null, 'live_required' => 0, 'release_at' => null];
    if ($type === 'live') {
        $f['starts_at'] = parse_local_dt((string)($post['starts_at'] ?? ''));
        if (!$f['starts_at']) return [$f, 'Informe a data e a hora da aula ao vivo.'];
        $url = trim((string)($post['live_url'] ?? ''));
        if ($url !== '') {
            $f['live_url'] = safe_live_url($url);
            if (!$f['live_url']) return [$f, 'O link da sala precisa começar com http:// ou https:// (Zoom, Google Meet, Teams, YouTube ao vivo...).'];
        }
        $f['live_required'] = isset($post['live_required']) ? 1 : 0;
    } else {
        $rel = trim((string)($post['release_at'] ?? ''));
        if ($rel !== '') {
            $f['release_at'] = parse_local_dt($rel);
            if (!$f['release_at']) return [$f, 'Data de liberação inválida.'];
        }
    }
    return [$f, ''];
}
