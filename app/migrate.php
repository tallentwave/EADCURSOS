<?php
declare(strict_types=1);

const SCHEMA_VERSION = 2;

/** Atualiza bancos de instalações anteriores. Idempotente; roda uma vez por versão. */
function ensure_schema(): void
{
    if ((int)setting('schema_version', '1') >= SCHEMA_VERSION) return;

    $stmts = [
        "ALTER TABLE lessons ADD COLUMN duration_sec INT NOT NULL DEFAULT 0 AFTER duration_min",
        "ALTER TABLE orders ADD COLUMN coupon_id INT UNSIGNED NULL AFTER mp_payment_id",
        "ALTER TABLE orders ADD COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER coupon_id",
        "ALTER TABLE enrollments MODIFY source ENUM('purchase','admin','voucher') NOT NULL DEFAULT 'admin'",
    ];
    foreach ($stmts as $sql) {
        try { db()->exec($sql); }
        catch (PDOException $e) { if (!in_array($e->errorInfo[1] ?? 0, [1060], true)) throw $e; } // 1060 = coluna já existe
    }
    $schema = file_get_contents(ROOT . '/install/schema.sql');
    foreach (array_filter(array_map('trim', explode(";\n", $schema))) as $sql) {
        if (preg_match('/^CREATE TABLE IF NOT EXISTS (coupons|vouchers|lesson_watch)\b/', $sql)) db()->exec($sql);
    }
    set_setting('schema_version', (string)SCHEMA_VERSION);
}
