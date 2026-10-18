<?php
declare(strict_types=1);

require_once ROOT . '/app/lib/qrcode.php';

// ---------- CPF (LGPD: guardado criptografado, exibido sempre mascarado) ----------
function cpf_digits(string $s): string { return preg_replace('/\D/', '', $s); }

function cpf_valid(string $cpf): bool
{
    $d = cpf_digits($cpf);
    if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) return false;
    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) $sum += (int)$d[$i] * ($t + 1 - $i);
        if (((($sum * 10) % 11) % 10) !== (int)$d[$t]) return false;
    }
    return true;
}
/** 52998224725 => ***.982.247-** (só 6 dígitos centrais). */
function cpf_mask(string $cpf): string
{
    $d = cpf_digits($cpf);
    return strlen($d) === 11 ? '***.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-**' : '';
}
function cpf_hash(string $cpf): string { return hash_hmac('sha256', cpf_digits($cpf), APP_KEY); }
function user_cpf(array $u): string { return !empty($u['cpf_enc']) ? secret_decrypt($u['cpf_enc']) : ''; }

/** @return string erro ou '' se salvou */
function set_user_cpf(int $uid, string $cpf): string
{
    if (!cpf_valid($cpf)) return 'CPF inválido.';
    $h = cpf_hash($cpf);
    if (q_val('SELECT 1 FROM users WHERE cpf_hash = ? AND id <> ?', [$h, $uid])) return 'Este CPF já está vinculado a outra conta.';
    q('UPDATE users SET cpf_enc = ?, cpf_hash = ? WHERE id = ?', [secret_encrypt(cpf_digits($cpf)), $h, $uid]);
    return '';
}

// ---------- Certificados ----------
function course_workload_min(int $courseId): int
{
    return (int)round((float)q_val('SELECT COALESCE(SUM(IF(l.duration_sec > 0, l.duration_sec / 60, l.duration_min)), 0)
        FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = ?', [$courseId]));
}
function fmt_workload(int $min): string
{
    if ($min <= 0) return '';
    $h = intdiv($min, 60); $m = $min % 60;
    return $h > 0 ? $h . 'h' . ($m ? sprintf('%02d', $m) . 'min' : '') : $m . ' min';
}
function cert_code_normalize(string $c): string
{
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c));
    return preg_match('/^[A-F0-9]{12}$|^[A-F0-9]{16}$/', $c) ? $c : '';
}
function cert_code_display(string $c): string { return implode('-', str_split($c, 4)); }
function cert_public_url(string $code): string { return url('verificar.php?codigo=' . $code); }

/** QR Code como SVG inline (sem serviços externos; funciona offline e na impressão). */
function qr_svg(string $text, int $px = 120): string
{
    $qr = QRCode::getMinimumQRCode($text, QR_ERROR_CORRECT_LEVEL_M);
    $n = $qr->getModuleCount();
    $q = 4; // zona silenciosa
    $path = '';
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            if (!$qr->isDark($r, $c)) continue;
            $run = 1;
            while ($c + $run < $n && $qr->isDark($r, $c + $run)) $run++;
            $path .= 'M' . ($c + $q) . ' ' . ($r + $q) . 'h' . $run . 'v1h-' . $run . 'z';
            $c += $run - 1;
        }
    }
    $size = $n + 2 * $q;
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $px . '" height="' . $px . '" viewBox="0 0 ' . $size . ' ' . $size . '" shape-rendering="crispEdges" role="img" aria-label="QR Code de validação do certificado">'
        . '<rect width="' . $size . '" height="' . $size . '" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
}

/** Emite (ou devolve) o certificado congelando os dados do titular. */
function issue_certificate(array $user, array $course): array
{
    $uid = (int)$user['id']; $cid = (int)$course['id'];
    $cert = q_one('SELECT * FROM certificates WHERE user_id = ? AND course_id = ?', [$uid, $cid]);
    $cpf = user_cpf(q_one('SELECT cpf_enc FROM users WHERE id = ?', [$uid]) ?: []);
    if (!$cert) {
        $fe = final_exam($cid);
        $score = $fe && exam_ready($fe) ? exam_best($uid, (int)$fe['id']) : null;
        q('INSERT INTO certificates (user_id, course_id, code, issued_at, holder_name, holder_cpf_masked, course_title, workload_min, final_score, completed_at)
           VALUES (?,?,?,?,?,?,?,?,?,?)', [$uid, $cid, strtoupper(bin2hex(random_bytes(8))), now(), $user['name'], cpf_mask($cpf) ?: null, $course['title'], course_workload_min($cid), $score, now()]);
        $cert = q_one('SELECT * FROM certificates WHERE user_id = ? AND course_id = ?', [$uid, $cid]);
    } elseif ($cert['holder_cpf_masked'] === null && $cpf !== '') { // certificado antigo: completa o CPF uma única vez
        q('UPDATE certificates SET holder_cpf_masked = ? WHERE id = ?', [cpf_mask($cpf), $cert['id']]);
        $cert['holder_cpf_masked'] = cpf_mask($cpf);
    }
    return $cert;
}

// ---------- Direitos do titular (LGPD art. 18) ----------
function export_user_data(int $uid): array
{
    $u = q_one('SELECT * FROM users WHERE id = ?', [$uid]) ?: [];
    return [
        'gerado_em' => now(),
        'conta' => ['nome' => $u['name'] ?? '', 'email' => $u['email'] ?? '', 'cpf' => user_cpf($u), 'perfil' => $u['role'] ?? '',
                    'cadastro' => $u['created_at'] ?? '', 'ultimo_login' => $u['last_login'] ?? '', 'consentimento_lgpd_em' => $u['lgpd_consent_at'] ?? ''],
        'foto_carteirinha' => (($pb = user_photo_bytes($u['photo'] ?? null)) !== null ? 'data:image/jpeg;base64,' . base64_encode($pb) : null),
        'foto_consentimento_em' => $u['photo_consent_at'] ?? null,
        'matriculas' => q_all('SELECT c.title AS curso, e.source AS origem, e.created_at, e.expires_at FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.user_id = ?', [$uid]),
        'pedidos' => q_all('SELECT o.id, c.title AS curso, o.amount AS valor, o.discount AS desconto, o.status, o.created_at, o.paid_at FROM orders o JOIN courses c ON c.id = o.course_id WHERE o.user_id = ?', [$uid]),
        'progresso' => q_all('SELECT l.title AS aula, p.completed AS concluida, p.updated_at FROM progress p JOIN lessons l ON l.id = p.lesson_id WHERE p.user_id = ?', [$uid]),
        'provas' => q_all('SELECT e.title AS prova, a.started_at, a.finished_at, a.score AS nota, a.passed AS aprovado FROM exam_attempts a JOIN exams e ON e.id = a.exam_id WHERE a.user_id = ?', [$uid]),
        'certificados' => q_all('SELECT code AS codigo, course_title AS curso, issued_at AS emissao, status, show_details AS exibe_dados_publicos FROM certificates WHERE user_id = ?', [$uid]),
        'comentarios' => q_all('SELECT body AS texto, created_at FROM comments WHERE user_id = ?', [$uid]),
    ];
}

/** Exclusão: remove dados pessoais e conteúdo; mantém pedidos anonimizados (obrigação fiscal). */
function erase_user(int $uid): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $email = q_val('SELECT email FROM users WHERE id = ?', [$uid]);
        if (($pp = user_photo_path(q_val('SELECT photo FROM users WHERE id = ?', [$uid])))) @unlink($pp);
        foreach (['certificates', 'comments', 'progress', 'lesson_watch', 'exam_attempts', 'enrollments', 'password_resets'] as $t) q("DELETE FROM $t WHERE user_id = ?", [$uid]);
        if ($email) q('DELETE FROM login_attempts WHERE email = ?', [$email]);
        q('UPDATE vouchers SET redeemed_by = NULL WHERE redeemed_by = ?', [$uid]);
        q('UPDATE users SET name = "Usuário removido", email = ?, password_hash = ?, status = "blocked", cpf_enc = NULL, cpf_hash = NULL, lgpd_consent_at = NULL, photo = NULL, photo_consent_at = NULL WHERE id = ?',
            ['removido-' . $uid . '@invalid.local', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $uid]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}
