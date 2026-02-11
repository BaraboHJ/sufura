<?php

namespace App\Models;

use PDO;

class Dish
{
    const DEFAULT_CATEGORY_COLUMN = 'dish_category_id';

    /** @var string|null */
    private static $resolvedCategoryColumn = null;

    public static function create(PDO $pdo, $orgId, $actorUserId, array $payload)
    {
        $categoryColumn = self::categoryColumn($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO dishes (org_id, name, {$categoryColumn}, description, yield_servings, active, created_at)
             VALUES (:org_id, :name, :category_id, :description, :yield_servings, :active, NOW())"
        );

        $yieldServings = isset($payload['yield_servings']) ? $payload['yield_servings'] : 1;
        $active = isset($payload['active']) ? $payload['active'] : 1;

        $stmt->execute(array(
            'org_id' => (int) $orgId,
            'name' => $payload['name'],
            'category_id' => $payload['category_id'],
            'description' => $payload['description'],
            'yield_servings' => $yieldServings,
            'active' => $active,
        ));

        $id = (int) $pdo->lastInsertId();
        $dish = self::findById($pdo, (int) $orgId, $id);

        Audit::log($pdo, (int) $orgId, (int) $actorUserId, 'dish', $id, 'create', null, $dish);

        return $dish ? $dish : array();
    }

    public static function update(PDO $pdo, $orgId, $actorUserId, $id, array $payload)
    {
        $before = self::findById($pdo, (int) $orgId, (int) $id);
        $categoryColumn = self::categoryColumn($pdo);

        $stmt = $pdo->prepare(
            "UPDATE dishes
             SET name = :name,
                 {$categoryColumn} = :category_id,
                 description = :description,
                 yield_servings = :yield_servings,
                 active = :active,
                 updated_at = NOW()
             WHERE org_id = :org_id
               AND id = :id"
        );

        $yieldServings = isset($payload['yield_servings']) ? $payload['yield_servings'] : 1;
        $active = isset($payload['active']) ? $payload['active'] : 1;

        $stmt->execute(array(
            'name' => $payload['name'],
            'category_id' => $payload['category_id'],
            'description' => $payload['description'],
            'yield_servings' => $yieldServings,
            'active' => $active,
            'org_id' => (int) $orgId,
            'id' => (int) $id,
        ));

        $after = self::findById($pdo, (int) $orgId, (int) $id);
        Audit::log($pdo, (int) $orgId, (int) $actorUserId, 'dish', (int) $id, 'update', $before, $after);

        return $after ? $after : array();
    }

    public static function findById(PDO $pdo, $orgId, $id)
    {
        $categoryColumn = self::categoryColumn($pdo);

        $stmt = $pdo->prepare(
            "SELECT d.id,
                    d.org_id,
                    d.name,
                    d.{$categoryColumn} AS category_id,
                    c.name AS category_name,
                    d.description,
                    d.yield_servings,
                    d.active,
                    d.created_at,
                    d.updated_at
             FROM dishes d
             JOIN dish_categories c ON c.id = d.{$categoryColumn}
                                   AND c.org_id = d.org_id
             WHERE d.org_id = :org_id
               AND d.id = :id"
        );

        $stmt->execute(array(
            'org_id' => (int) $orgId,
            'id' => (int) $id,
        ));

        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    public static function listByOrg(PDO $pdo, $orgId)
    {
        $categoryColumn = self::categoryColumn($pdo);

        $stmt = $pdo->prepare(
            "SELECT d.id,
                    d.name,
                    d.{$categoryColumn} AS category_id,
                    c.name AS category_name,
                    d.description,
                    d.yield_servings,
                    d.active,
                    d.created_at,
                    d.updated_at
             FROM dishes d
             JOIN dish_categories c ON c.id = d.{$categoryColumn}
                                   AND c.org_id = d.org_id
             WHERE d.org_id = :org_id
             ORDER BY c.name ASC, d.name ASC"
        );

        $stmt->execute(array('org_id' => (int) $orgId));

        return $stmt->fetchAll();
    }

    public static function searchByOrg(PDO $pdo, $orgId, $categoryId = 0, $query = '')
    {
        $categoryColumn = self::categoryColumn($pdo);

        $sql = "SELECT id, name, description, yield_servings, {$categoryColumn} AS category_id
                FROM dishes
                WHERE org_id = :org_id";

        $params = array('org_id' => (int) $orgId);

        if ((int) $categoryId > 0) {
            $sql .= " AND {$categoryColumn} = :category_id";
            $params['category_id'] = (int) $categoryId;
        }

        if ($query !== '') {
            $sql .= ' AND name LIKE :query';
            $params['query'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function categoryColumn(PDO $pdo)
    {
        if (self::$resolvedCategoryColumn !== null) {
            return self::$resolvedCategoryColumn;
        }

        if (self::dishesTableHasColumn($pdo, 'dish_category_id')) {
            self::$resolvedCategoryColumn = 'dish_category_id';
            return self::$resolvedCategoryColumn;
        }

        if (self::dishesTableHasColumn($pdo, 'category_id')) {
            self::$resolvedCategoryColumn = 'category_id';
            return self::$resolvedCategoryColumn;
        }

        self::$resolvedCategoryColumn = self::DEFAULT_CATEGORY_COLUMN;
        return self::$resolvedCategoryColumn;
    }

    private static function dishesTableHasColumn(PDO $pdo, $column)
    {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1'
        );

        if (!$stmt) {
            return false;
        }

        $stmt->execute(array(
            'table_name' => 'dishes',
            'column_name' => $column,
        ));

        return (bool) $stmt->fetchColumn();
    }

    public static function delete(PDO $pdo, $orgId, $actorUserId, $id)
    {
        $before = self::findById($pdo, (int) $orgId, (int) $id);

        $stmt = $pdo->prepare('DELETE FROM dishes WHERE org_id = :org_id AND id = :id');
        $stmt->execute(array('org_id' => (int) $orgId, 'id' => (int) $id));

        Audit::log($pdo, (int) $orgId, (int) $actorUserId, 'dish', (int) $id, 'delete', $before, null);
    }

    public static function hasMenuItems(PDO $pdo, $orgId, $id)
    {
        $stmt = $pdo->prepare('SELECT 1 FROM menu_items WHERE org_id = :org_id AND dish_id = :id LIMIT 1');
        $stmt->execute(array('org_id' => (int) $orgId, 'id' => (int) $id));

        return (bool) $stmt->fetchColumn();
    }

    public static function hasMenuSnapshots(PDO $pdo, $orgId, $id)
    {
        $stmt = $pdo->prepare('SELECT 1 FROM menu_item_cost_snapshots WHERE org_id = :org_id AND dish_id = :id LIMIT 1');
        $stmt->execute(array('org_id' => (int) $orgId, 'id' => (int) $id));

        return (bool) $stmt->fetchColumn();
    }
}
