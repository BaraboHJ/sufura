<?php

namespace App\Models;

use PDO;

class CostImport
{
    public static function create(PDO $pdo, int $orgId, int $actorUserId, string $originalFilename): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO cost_imports (org_id, uploaded_by_user_id, original_filename, status, created_at)
             VALUES (:org_id, :uploaded_by_user_id, :original_filename, :status, NOW())'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'uploaded_by_user_id' => $actorUserId,
            'original_filename' => $originalFilename,
            'status' => 'uploaded',
        ]);

        $id = (int) $pdo->lastInsertId();
        return self::findById($pdo, $orgId, $id) ?? [];
    }

    public static function findById(PDO $pdo, int $orgId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, org_id, uploaded_by_user_id, original_filename, status, created_at
             FROM cost_imports
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listWithSummary(PDO $pdo, int $orgId): array
    {
        $stmt = $pdo->prepare(
            'SELECT ci.id,
                    ci.original_filename,
                    ci.status,
                    ci.created_at,
                    COUNT(cir.id) AS total_rows,
                    SUM(cir.parse_status = "matched_ok") AS matched_ok,
                    SUM(cir.parse_status = "missing_ingredient") AS missing_ingredient,
                    SUM(cir.parse_status = "invalid_uom") AS invalid_uom,
                    SUM(cir.parse_status = "invalid_number") AS invalid_number
             FROM cost_imports ci
             LEFT JOIN cost_import_rows cir ON cir.import_id = ci.id AND cir.org_id = ci.org_id
             WHERE ci.org_id = :org_id
             GROUP BY ci.id
             ORDER BY ci.created_at DESC, ci.id DESC'
        );
        $stmt->execute(['org_id' => $orgId]);
        return $stmt->fetchAll();
    }

    public static function rowsForImport(PDO $pdo, int $orgId, int $importId): array
    {
        $stmt = $pdo->prepare(
            'SELECT cir.id,
                    cir.row_num,
                    cir.ingredient_name_raw,
                    cir.matched_ingredient_id,
                    cir.purchase_qty,
                    cir.purchase_uom_symbol,
                    cir.purchase_uom_id,
                    cir.total_cost_minor,
                    cir.parse_status,
                    cir.error_message,
                    cir.computed_cost_per_base_x10000,
                    i.name AS ingredient_name
             FROM cost_import_rows cir
             LEFT JOIN ingredients i ON i.id = cir.matched_ingredient_id AND i.org_id = cir.org_id
             WHERE cir.org_id = :org_id AND cir.import_id = :import_id
             ORDER BY cir.row_num ASC, cir.id ASC'
        );
        $stmt->execute(['org_id' => $orgId, 'import_id' => $importId]);
        return $stmt->fetchAll();
    }

    public static function updateStatus(PDO $pdo, int $orgId, int $importId, string $status): void
    {
        $stmt = $pdo->prepare(
            'UPDATE cost_imports SET status = :status WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute(['status' => $status, 'org_id' => $orgId, 'id' => $importId]);
    }
}
