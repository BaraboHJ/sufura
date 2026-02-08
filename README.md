# Sufura (Menu Costing App)

## Setup (Shared Hosting — No CLI)
1. **Upload files**
   - Upload the entire project to your hosting account.
   - Set your document root to the `public/` directory.
     - If your host can’t change the document root, move the contents of `public/` into your web root and keep the rest of the project one level above it.

2. **Create config**
   - Copy `config/config.example.php` to `config/config.php` using your file manager or FTP.
   - Open `config/config.php` and update the database credentials for your hosting account.

3. **Create database**
   - In your hosting control panel (e.g., cPanel), create a MySQL database and user, then grant the user full privileges to the database.

4. **Import schema + seed**
   - Open phpMyAdmin (or your host’s database tool).
   - Import the SQL file at `sql/schema.sql`, then import `sql/seed.sql`.

5. **Visit the site**
   - Navigate to your domain (e.g., `https://your-domain.com`).

## Default Login
- Email: `admin@example.com`
- Password: `admin123!`

**Important:** Change this password after first login.

## Notes
- All monetary values are stored in minor units (integers) with currency columns.
- All records are scoped to `org_id` for multi-tenancy.
- CSRF protection is enforced for all POST/PUT/PATCH/DELETE requests.

## Bulk Cost Import (CSV)
1. Go to **Cost Imports** → **New Import**.
2. Upload a CSV with headers (case-insensitive):
   - `ingredient_name`
   - `purchase_qty`
   - `purchase_uom`
   - `total_cost`
3. Review the preview table and confirm the import.
4. If any ingredients were updated today, check the overwrite confirmation box before applying.

The import parser trims and normalizes ingredient names, matches units by symbol within the ingredient’s UOM set, and computes cost per base unit from the purchase quantity and total cost.
