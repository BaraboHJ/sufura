# Sufura (Menu Costing App)

## Setup
1. Create a database and import schema + seed:
   ```bash
   mysql -u root -p < sql/001_init.sql
   mysql -u root -p < sql/002_seed.sql
   ```

2. Copy config and update credentials:
   ```bash
   cp config/config.example.php config/config.php
   ```

3. Start the PHP built-in server:
   ```bash
   php -S localhost:8000 -t public
   ```

4. Visit http://localhost:8000

## Default Login
- Email: `admin@example.com`
- Password: `admin123!`

**Important:** Change this password after first login.

## Notes
- All monetary values are stored in minor units (integers) with currency columns.
- All records are scoped to `org_id` for multi-tenancy.
- CSRF protection is enforced for all POST/PUT/PATCH/DELETE requests.
