<?php
declare(strict_types=1);

function has_access(?int $uid, int $courseId): bool
{
    if (!$uid) return false;
    $u = current_user();
    if ($u && (int)$u['id'] === $uid && $u['role'] === 'admin') return true;
    return (bool)q_val('SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ? AND (expires_at IS NULL OR expires_at > ?)', [$uid, $courseId, now()]);
}

function grant_access(int $uid, int $courseId, string $source = 'admin', ?int $orderId = null): void
{
    q('INSERT INTO enrollments (user_id, course_id, source, order_id, created_at) VALUES (?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE source = VALUES(source), order_id = COALESCE(VALUES(order_id), order_id)',
        [$uid, $courseId, $source, $orderId, now()]);
}

function course_by_id(int $id): ?array { return q_one('SELECT * FROM courses WHERE id = ?', [$id]); }

function course_lessons(int $courseId): array
{
    return q_all('SELECT l.*, m.title AS module_title, m.position AS mpos FROM lessons l
                  JOIN modules m ON m.id = l.module_id WHERE m.course_id = ?
                  ORDER BY m.position, m.id, l.position, l.id', [$courseId]);
}

/** @return array{total:int,done:int,pct:int} */
function course_progress(int $uid, int $courseId): array
{
    $counted = 'NOT (l.lesson_type = "live" AND l.live_required = 0)';   // ao vivo opcional não conta
    $total = (int)q_val("SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = ? AND $counted", [$courseId]);
    $done = (int)q_val("SELECT COUNT(*) FROM progress p JOIN lessons l ON l.id = p.lesson_id JOIN modules m ON m.id = l.module_id
                        WHERE p.user_id = ? AND m.course_id = ? AND p.completed = 1 AND $counted", [$uid, $courseId]);
    return ['total' => $total, 'done' => $done, 'pct' => $total ? (int)floor($done * 100 / $total) : 0];
}

/** Próxima aula não concluída (ou a primeira). */
function next_lesson(int $uid, int $courseId): ?array
{
    $done = array_column(q_all('SELECT lesson_id FROM progress WHERE user_id = ? AND completed = 1', [$uid]), 'lesson_id');
    $done = array_flip($done);
    $first = null;
    foreach (course_lessons($courseId) as $l) {
        $first ??= $l;
        if (!isset($done[$l['id']]) && lesson_counts_for_completion($l)) return $l;   // ao vivo opcional não é "a próxima"
    }
    return $first;
}

function course_card(array $c, bool $owned, ?int $pct = null): string
{
    $href = $owned && isset($c['next_lesson_id']) ? 'aula.php?id=' . (int)$c['next_lesson_id'] : 'curso.php?id=' . (int)$c['id'];
    $h = '<a class="card' . ($owned ? '' : ' locked') . '" href="' . e(url($href)) . '">';
    $h .= '<img loading="lazy" src="' . e(img_url($c['cover'])) . '" alt="">';
    if (!$owned) $h .= '<span class="lock">&#128274;</span>';
    $h .= '<div class="card-info"><strong>' . e($c['title']) . '</strong>';
    $h .= '<small>' . e($c['short_desc']) . '</small>';
    if ($owned && $pct !== null) $h .= '<div class="bar"><i style="width:' . $pct . '%"></i></div><small>' . $pct . '% concluído</small>';
    elseif (!$owned) $h .= '<span class="price">' . ((float)$c['price'] > 0 ? money($c['price']) : 'Grátis') . '</span>';
    return $h . '</div></a>';
}
