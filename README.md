# UmaLink: Agriculture Information System

AP3 Midterm Project, Agriculture Information Systems, Cebu Technological University.

A web-based information system that manages **Farmers, Farms, Crops, Planting Records and Harvest Records**
on a normalized **Supabase PostgreSQL** database. Built with PHP 8 (PDO), Bootstrap 5, Chart.js and vanilla JavaScript.

## Features
Dashboard (live totals, charts, recent records) | CRUD for all five modules | search, filters, sorting, pagination |
lookup tables with dropdowns (description shown, numeric ID stored) | six database-driven reports with date filters |
login with Administrator and Staff roles | input validation (application + database) | delete confirmation and
referential-integrity protection | created/updated audit fields and an audit log.

## Requirements
- PHP 8.1 or later with the extensions **pdo_pgsql**, **pgsql** and **mbstring**
- A Supabase project (free tier is enough)
- No Composer or npm needed: Bootstrap and Chart.js are already included in `assets/vendor/`

Enable the PostgreSQL extensions in `php.ini` (XAMPP/Windows): remove the `;` before `extension=pdo_pgsql` and `extension=pgsql`, then restart.

## 1. Set up the Supabase database
1. In Supabase open **SQL Editor** and run these files **in this order** (paste each one and press Run):
   1. `database/schema.sql`: tables, keys, constraints, triggers
   2. `database/lookup_data.sql`: lookup values (roles, soil, irrigation, categories, seasons, status, grades, remarks)
   3. `database/seed.sql`: sample records and the two demo accounts
2. Open **Project Settings > Database > Connection string** and copy the host, port, user and password.
   Use the **Session pooler** string (port 5432). It works over IPv4; the direct `db.<ref>.supabase.co` host needs IPv6.

## 2. Configure credentials (never commit them)
Copy `.env.example` to `.env` and fill in your values:

```
DB_HOST=aws-0-<region>.pooler.supabase.com
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres.<your_project_ref>
DB_PASSWORD=<your_database_password>
DB_SSLMODE=require
APP_BASE_URL=
```
`.env` is listed in `.gitignore`. `APP_BASE_URL` stays empty when the project folder is the web root; set it to
`/Agriculture_System` when the folder sits inside `htdocs` (XAMPP). Real environment variables also work.

## 3. Run
```
cd Agriculture_System
php -S localhost:8000 router.php
```
Open http://localhost:8000. `router.php` blocks direct access to `.env`, `.sql` and the config folders.
(On Apache/XAMPP the included `.htaccess` does the same.)

## Demo accounts (change these passwords after first login)
| Username | Password   | Role          | Can do |
|----------|------------|---------------|--------|
| admin    | Admin@123  | Administrator | Everything, including Delete and User Accounts |
| staff    | Staff@123  | Staff         | View, add, edit, search, reports (no delete, no user accounts) |

Create or change accounts under **Administration > User Accounts**. Passwords are stored with `password_hash()` (bcrypt).

## Database notes
- Fields with predefined values use lookup tables (`soil_type`, `irrigation_type`, `crop_category`, `season`,
  `planting_status`, `quality_grade`, `harvest_remark`, `role`). Forms show the description; the record stores the ID.
- Foreign keys use `ON DELETE RESTRICT`, so linked records cannot be deleted by accident.
- A trigger rejects a harvest dated before its planting date; another keeps `updated_at` current.
- `seed.sql`: Planting records 1-10 and 29-30 were not visible in the project file's sample tables, so they were
  generated from the pattern of the visible rows (farm/crop IDs, dates, areas, seed quantities, expected dates).
  Everything else is taken from the project file.
- Supabase Row Level Security is not needed because the app connects with the database role from the server only.
  Never expose the database password in browser code.

## Folder structure
```
config/      database.php (PDO), settings.php        includes/   header, sidebar, footer, functions
database/    schema.sql, lookup_data.sql, seed.sql   auth/       login.php, logout.php
farmers/ farms/ crops/ planting/ harvest/            index, add, view, edit, delete (+ form.php shared by add/edit)
reports/     index.php (6 reports)                   users/      administrators only
assets/      css, js, vendor (Bootstrap, Chart.js)   index.php   dashboard
```
