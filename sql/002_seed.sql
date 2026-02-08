USE sufura;

INSERT INTO organizations (name, default_currency, timezone, default_waste_pct, stale_cost_days)
VALUES ('Demo Org', 'MVR', 'UTC', 0.1200, 30);

INSERT INTO users (org_id, name, email, role, status, password_hash)
VALUES (1, 'Admin User', 'admin@example.com', 'admin', 'active', '$2y$12$uy/VBpc4dQV2aAV27i1ZE.M88aoY0pSA55qChFLIcik/xy4eq12Se');

INSERT INTO uom_sets (org_id, name) VALUES
(1, 'Mass'),
(1, 'Volume'),
(1, 'Count');

INSERT INTO uoms (org_id, uom_set_id, name, symbol, factor_to_base, is_base) VALUES
(1, 1, 'Milligram', 'mg', 0.001000, 0),
(1, 1, 'Gram', 'g', 1.000000, 1),
(1, 1, 'Kilogram', 'kg', 1000.000000, 0),
(1, 2, 'Milliliter', 'ml', 1.000000, 1),
(1, 2, 'Liter', 'l', 1000.000000, 0),
(1, 3, 'Piece', 'pc', 1.000000, 1);
