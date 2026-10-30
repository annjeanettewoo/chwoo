# CHWOO database setup

## Files

- `schema.sql` creates `chwoo_db` and the `products`, `customers`, `orders` and `order_items` tables, including their keys and relationships.
- `seed_products.sql` selects `chwoo_db` and inserts the six initial sample products. Stock quantities are demonstration values. Image paths are relative to the project root; this SQL does not create image files.

## Fresh installation with phpMyAdmin

Use these steps for a new installation. If `chwoo_db` already contains data, back it up first and review the limitations below before importing anything.

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin/` and sign in if required.
3. From the server-level Import tab, choose `database/schema.sql`, keep the format as SQL, and select Go. This file creates and selects `chwoo_db`; the database account needs permission to create databases and tables.
4. Confirm that the import completed successfully and that all four tables exist in `chwoo_db`.
5. Select `chwoo_db`, open Import, choose `database/seed_products.sql`, and select Go. This file also explicitly selects `chwoo_db`.
6. Confirm that the seed import completed successfully. For a fresh installation, the products table should contain the six sample rows with product IDs 1 through 6.

Always import `schema.sql` before `seed_products.sql`. Stop and inspect any import errors before proceeding.

## Back up before subsequent changes

1. In phpMyAdmin, select the existing `chwoo_db` database and open Export.
2. Choose Custom and SQL format, select all tables, and include both structure and data.
3. Export the database to a dated SQL backup outside the repository. Verify that the download completed and retain it before applying any changes.
4. If restoration needs to be tested, use a separate isolated MySQL instance. Review the backup's database-selection and destructive statements before importing it; do not test a restore over the working database.

## Initial setup files are not migrations

`schema.sql` uses `CREATE DATABASE IF NOT EXISTS` and `CREATE TABLE IF NOT EXISTS`. Existing tables are left in place, so importing it again does not update their columns, indexes or relationships to match this file.

`seed_products.sql` preserves the original `INSERT IGNORE` statement. Conflicting rows can be skipped, and existing product values are not refreshed. Review import warnings; a completed import is not proof that an existing database matches the initial schema or sample data.

Neither file tracks which changes have already been applied. Do not use repeated imports as an automatic upgrade, reset or repair process.

Future deliberate schema updates belong in `database/migrations/`. Separate test-data insertion and reset scripts belong in `database/demo/`. Those directories and scripts are not part of this initial setup.
