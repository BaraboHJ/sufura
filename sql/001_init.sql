CREATE DATABASE IF NOT EXISTS sufura CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sufura;

CREATE TABLE organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    default_currency CHAR(3) NOT NULL DEFAULT 'USD',
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    default_waste_pct DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    stale_cost_days INT NOT NULL DEFAULT 30,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_users_org_email (org_id, email),
    INDEX idx_users_org (org_id),
    CONSTRAINT fk_users_org FOREIGN KEY (org_id) REFERENCES organizations(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    before_json JSON NULL,
    after_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_org (org_id),
    INDEX idx_audit_actor (actor_user_id),
    CONSTRAINT fk_audit_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE uom_sets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_uom_sets_org (org_id),
    CONSTRAINT fk_uom_sets_org FOREIGN KEY (org_id) REFERENCES organizations(id)
) ENGINE=InnoDB;

CREATE TABLE uoms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    uom_set_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    factor_to_base DECIMAL(18,6) NOT NULL,
    is_base TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_uoms_org (org_id),
    INDEX idx_uoms_set (uom_set_id),
    UNIQUE KEY uniq_uoms_set_symbol (uom_set_id, symbol),
    CONSTRAINT fk_uoms_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_uoms_set FOREIGN KEY (uom_set_id) REFERENCES uom_sets(id)
) ENGINE=InnoDB;

CREATE TABLE ingredients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    uom_set_id BIGINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_ingredients_org (org_id),
    UNIQUE KEY uniq_ingredients_org_name (org_id, name),
    CONSTRAINT fk_ingredients_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_ingredients_uom_set FOREIGN KEY (uom_set_id) REFERENCES uom_sets(id)
) ENGINE=InnoDB;

CREATE TABLE ingredient_costs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    ingredient_id BIGINT UNSIGNED NOT NULL,
    cost_per_base_x10000 INT NOT NULL,
    currency CHAR(3) NOT NULL,
    effective_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ingredient_costs_org (org_id),
    INDEX idx_ingredient_costs_ingredient (ingredient_id),
    INDEX idx_ingredient_costs_effective (effective_at),
    CONSTRAINT fk_ingredient_costs_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_ingredient_costs_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
) ENGINE=InnoDB;

CREATE TABLE dishes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    yield_servings INT NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_dishes_org (org_id),
    CONSTRAINT fk_dishes_org FOREIGN KEY (org_id) REFERENCES organizations(id)
) ENGINE=InnoDB;

CREATE TABLE dish_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    dish_id BIGINT UNSIGNED NOT NULL,
    ingredient_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(18,6) NOT NULL,
    uom_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    waste_pct DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dish_lines_org (org_id),
    INDEX idx_dish_lines_dish (dish_id),
    CONSTRAINT fk_dish_lines_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_dish_lines_dish FOREIGN KEY (dish_id) REFERENCES dishes(id),
    CONSTRAINT fk_dish_lines_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
    CONSTRAINT fk_dish_lines_uom FOREIGN KEY (uom_id) REFERENCES uoms(id)
) ENGINE=InnoDB;

CREATE TABLE menus (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    servings INT NOT NULL DEFAULT 1,
    cost_mode ENUM('live','locked') NOT NULL DEFAULT 'live',
    locked_at DATETIME NULL,
    locked_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_menus_org (org_id),
    CONSTRAINT fk_menus_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_menus_locked_by FOREIGN KEY (locked_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE menu_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    menu_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_menu_groups_org (org_id),
    INDEX idx_menu_groups_menu (menu_id),
    CONSTRAINT fk_menu_groups_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_menu_groups_menu FOREIGN KEY (menu_id) REFERENCES menus(id)
) ENGINE=InnoDB;

CREATE TABLE menu_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    menu_group_id BIGINT UNSIGNED NOT NULL,
    dish_id BIGINT UNSIGNED NOT NULL,
    uptake_pct DECIMAL(5,4) NOT NULL DEFAULT 1.0000,
    portion DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    waste_pct DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_menu_items_org (org_id),
    INDEX idx_menu_items_group (menu_group_id),
    CONSTRAINT fk_menu_items_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_menu_items_group FOREIGN KEY (menu_group_id) REFERENCES menu_groups(id),
    CONSTRAINT fk_menu_items_dish FOREIGN KEY (dish_id) REFERENCES dishes(id)
) ENGINE=InnoDB;

CREATE TABLE menu_cost_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    menu_id BIGINT UNSIGNED NOT NULL,
    menu_cost_per_pax_minor INT NOT NULL,
    currency CHAR(3) NOT NULL,
    locked_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_menu_cost_snapshots_org (org_id),
    INDEX idx_menu_cost_snapshots_menu (menu_id),
    CONSTRAINT fk_menu_cost_snapshots_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_menu_cost_snapshots_menu FOREIGN KEY (menu_id) REFERENCES menus(id),
    CONSTRAINT fk_menu_cost_snapshots_user FOREIGN KEY (locked_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE menu_item_cost_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    menu_id BIGINT UNSIGNED NOT NULL,
    menu_item_id BIGINT UNSIGNED NOT NULL,
    dish_id BIGINT UNSIGNED NOT NULL,
    dish_cost_per_serving_minor INT NOT NULL,
    uptake_pct DECIMAL(5,4) NOT NULL,
    portion DECIMAL(10,4) NOT NULL,
    waste_pct DECIMAL(5,4) NOT NULL,
    item_cost_per_pax_minor INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_menu_item_cost_snapshots_org (org_id),
    INDEX idx_menu_item_cost_snapshots_menu (menu_id),
    CONSTRAINT fk_menu_item_cost_snapshots_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_menu_item_cost_snapshots_menu FOREIGN KEY (menu_id) REFERENCES menus(id),
    CONSTRAINT fk_menu_item_cost_snapshots_item FOREIGN KEY (menu_item_id) REFERENCES menu_items(id),
    CONSTRAINT fk_menu_item_cost_snapshots_dish FOREIGN KEY (dish_id) REFERENCES dishes(id)
) ENGINE=InnoDB;

CREATE TABLE menu_ingredient_cost_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    menu_id BIGINT UNSIGNED NOT NULL,
    ingredient_id BIGINT UNSIGNED NOT NULL,
    ingredient_cost_id BIGINT UNSIGNED NOT NULL,
    cost_per_base_x10000 INT NOT NULL,
    base_uom_id BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    captured_at DATETIME NOT NULL,
    INDEX idx_menu_ing_snapshots_org (org_id),
    INDEX idx_menu_ing_snapshots_menu (menu_id),
    CONSTRAINT fk_menu_ing_snapshots_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_menu_ing_snapshots_menu FOREIGN KEY (menu_id) REFERENCES menus(id),
    CONSTRAINT fk_menu_ing_snapshots_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
    CONSTRAINT fk_menu_ing_snapshots_cost FOREIGN KEY (ingredient_cost_id) REFERENCES ingredient_costs(id),
    CONSTRAINT fk_menu_ing_snapshots_uom FOREIGN KEY (base_uom_id) REFERENCES uoms(id)
) ENGINE=InnoDB;

CREATE TABLE cost_imports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id BIGINT UNSIGNED NOT NULL,
    uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    status ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cost_imports_org (org_id),
    CONSTRAINT fk_cost_imports_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_cost_imports_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE cost_import_rows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_id BIGINT UNSIGNED NOT NULL,
    org_id BIGINT UNSIGNED NOT NULL,
    row_num INT NOT NULL,
    ingredient_name_raw VARCHAR(255) NOT NULL,
    matched_ingredient_id BIGINT UNSIGNED NULL,
    purchase_qty DECIMAL(18,6) NULL,
    purchase_uom_symbol VARCHAR(20) NULL,
    total_cost_minor INT NULL,
    parse_status ENUM('pending','matched','error') NOT NULL DEFAULT 'pending',
    error_message VARCHAR(255) NULL,
    computed_cost_per_base_x10000 INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cost_import_rows_import (import_id),
    INDEX idx_cost_import_rows_org (org_id),
    CONSTRAINT fk_cost_import_rows_import FOREIGN KEY (import_id) REFERENCES cost_imports(id),
    CONSTRAINT fk_cost_import_rows_org FOREIGN KEY (org_id) REFERENCES organizations(id),
    CONSTRAINT fk_cost_import_rows_ingredient FOREIGN KEY (matched_ingredient_id) REFERENCES ingredients(id)
) ENGINE=InnoDB;
