<?php
declare(strict_types=1);

const SCHEMA_VERSION = 5;

/** Atualiza bancos de instalações anteriores. Idempotente; roda uma vez por versão. */
function ensure_schema(): void
{
    if ((int)setting('schema_version', '1') >= SCHEMA_VERSION) return;

    $stmts = [
        "ALTER TABLE lessons ADD COLUMN duration_sec INT NOT NULL DEFAULT 0 AFTER duration_min",
        "ALTER TABLE orders ADD COLUMN coupon_id INT UNSIGNED NULL AFTER mp_payment_id",
        "ALTER TABLE orders ADD COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER coupon_id",
        "ALTER TABLE users ADD COLUMN cpf_enc VARCHAR(255) NULL",
        "ALTER TABLE users ADD COLUMN cpf_hash CHAR(64) NULL",
        "ALTER TABLE users ADD COLUMN lgpd_consent_at DATETIME NULL",
        "ALTER TABLE users ADD KEY idx_cpf_hash (cpf_hash)",
        "ALTER TABLE users ADD COLUMN photo VARCHAR(80) NULL",
        "ALTER TABLE users ADD COLUMN photo_consent_at DATETIME NULL",
        "ALTER TABLE certificates ADD COLUMN status ENUM('valid','revoked') NOT NULL DEFAULT 'valid'",
        "ALTER TABLE certificates ADD COLUMN revoked_at DATETIME NULL",
        "ALTER TABLE certificates ADD COLUMN show_details TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE certificates ADD COLUMN holder_name VARCHAR(120) NULL",
        "ALTER TABLE certificates ADD COLUMN holder_cpf_masked VARCHAR(20) NULL",
        "ALTER TABLE certificates ADD COLUMN course_title VARCHAR(190) NULL",
        "ALTER TABLE certificates ADD COLUMN workload_min INT NOT NULL DEFAULT 0",
        "ALTER TABLE certificates ADD COLUMN final_score DECIMAL(5,2) NULL",
        "ALTER TABLE certificates ADD COLUMN completed_at DATETIME NULL",
        "ALTER TABLE enrollments MODIFY source ENUM('purchase','admin','voucher') NOT NULL DEFAULT 'admin'",
    ];
    foreach ($stmts as $sql) {
        try { db()->exec($sql); }
        catch (PDOException $e) { if (!in_array($e->errorInfo[1] ?? 0, [1060, 1061], true)) throw $e; } // 1060/1061 = coluna/índice já existe
    }
    $schema = file_get_contents(ROOT . '/install/schema.sql');
    foreach (array_filter(array_map('trim', explode(";\n", $schema))) as $sql) {
        if (preg_match('/^CREATE TABLE IF NOT EXISTS (coupons|vouchers|lesson_watch|exams|exam_questions|exam_options|exam_attempts)\b/', $sql)) db()->exec($sql);
    }
    // certificados antigos: congela os dados do titular no momento da migração
    foreach (q_all('SELECT ce.id, u.name, c.title, c.id AS cid FROM certificates ce JOIN users u ON u.id = ce.user_id JOIN courses c ON c.id = ce.course_id WHERE ce.holder_name IS NULL') as $r) {
        q('UPDATE certificates SET holder_name = ?, course_title = ?, workload_min = ?, completed_at = issued_at WHERE id = ?', [$r['name'], $r['title'], course_workload_min((int)$r['cid']), $r['id']]);
    }
    set_setting('schema_version', (string)SCHEMA_VERSION);
}
