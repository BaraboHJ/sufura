<?php

namespace App\Models;

use PDO;

class Audit
{
    public static function log(
        PDO $pdo,
        ?int $orgId,
        ?int $actorUserId,
        string $entityType,
        ?int $entityId,
        string $action,
        ?array $beforeArray,
        ?array $afterArray
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (org_id, actor_user_id, entity_type, entity_id, action, before_json, after_json)
             VALUES (:org_id, :actor_user_id, :entity_type, :entity_id, :action, :before_json, :after_json)'
        );

        $stmt->execute([
            'org_id' => $orgId,
            'actor_user_id' => $actorUserId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'before_json' => $beforeArray ? json_encode($beforeArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_json' => $afterArray ? json_encode($afterArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}
