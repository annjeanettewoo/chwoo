# CHWOO database

CHWOO uses PHP/MySQLi with the `chwoo_db` database. The database has seven tables, with prices and images stored per colourway and inventory stored per colour/size variant. `schema.sql` defines the structure; `seed_products.sql` is the authoritative source for the demonstration catalogue.

## Fresh installation with phpMyAdmin

These scripts are for fresh installations, not incremental migrations. Do not import them over an existing populated database or combine them with an old seed/export. Both team members should use the same SQL files/revision.

**Deleting `chwoo_db` permanently removes its existing records, including customer and order records, unless they have been backed up. This procedure rebuilds the database; it does not preserve or migrate those records.**

1. Back up important existing database records and verify that the backup can be restored.
2. Only when intentionally performing a fresh installation, delete the existing `chwoo_db` through phpMyAdmin. Skip deletion if it does not exist.
3. In phpMyAdmin, import `database/schema.sql`. It creates/selects `chwoo_db` and creates the tables.
4. Import `database/seed_products.sql` into the same MariaDB instance.
5. Inspect both imports for errors/warnings and verify the seven tables and record counts below. A completion banner alone does not prove success.
6. With Apache and MySQL running in XAMPP, test the CHWOO catalogue, product colour/size selection, stock validation, and shopping bag add/increase/decrease/remove controls.

Connection settings are in `php/db.php`, which selects `chwoo_db` and sets `utf8mb4`. Check these settings against the local installation.

Repeated imports are not safe: `CREATE DATABASE IF NOT EXISTS` permits an empty database, but plain `CREATE TABLE` fails when tables already exist, and repeated seed inserts encounter duplicate primary/unique keys. DDL is not transactional, and an import client can continue after errors. Stop on failure and inspect database state before retrying; a final `COMMIT` does not prove that every statement succeeded. Use strict SQL mode when importing.

The documented target is XAMPP MariaDB 10.4.32. The schema uses InnoDB, `utf8mb4_unicode_ci`, CHECK constraints and MariaDB's `PERSISTENT` generated-column syntax. Actual local engine acceptance has not been tested in the static audit; compatibility with a different MySQL/MariaDB version must be verified before importing.

## Tables and integrity

| Table | Primary key | Data and relationships |
| --- | --- | --- |
| `products` | `product_id` | Garment name, category, description, optional fit information and active flag. No price, image or inventory column. |
| `size_guide` | `size_code` | Shared XS/S/M/L labels, optional guidance and display order. |
| `product_colours` | `colour_id` | `product_id` FK to `products`; colour name, `swatch_hex`, unique image path, decimal price and default flag. Unique `(product_id, colour_name)`. |
| `product_variants` | `variant_id` | `colour_id` FK to `product_colours`, `size_code` FK to `size_guide`, and stock quantity. Unique `(colour_id, size_code)`; the sole inventory authority. |
| `customers` | `customer_id` | Full name, unique email, password hash and creation timestamp. No demonstration accounts. |
| `orders` | `order_id` | `customer_id` FK to `customers`; recipient/contact/delivery snapshots, subtotal, shipping, total, payment/order states and timestamps. |
| `order_items` | `order_item_id` | `order_id` FK to `orders`, `variant_id` FK to `product_variants`; quantity, unit price and purchased name/size/colour snapshots. |

`product_colours.swatch_hex` has the exact SQL definition `swatch_hex CHAR(7) DEFAULT NULL`. It supplies the display swatch for a colourway. All seeded values use `#RRGGBB`; SQL does not enforce that format. `php/product.php` validates the format and uses a fallback display colour when necessary. These are preview swatches, not supplier colour specifications.

A product has colourways, and each seeded colourway has four size variants using the shared size codes. The seed's cross join creates every combination exactly once. Foreign keys and uniqueness prevent invalid references and duplicate selections; they do not automatically require four variants for a future colourway.

The generated `default_product_id` unique index permits at most one default colour per product while allowing multiple nondefaults. Each seeded product has exactly one default, but SQL does not enforce at least one after arbitrary edits. Change defaults transactionally, clearing the old default first. Products 7, 8 and 13 use colour IDs 7, 8 and 13 as their defaults.

All foreign keys use restrictive default delete/update behaviour. Deactivate referenced products rather than deleting them, and do not repurpose purchased variant or colour identities. Order-item product identity follows variant → colour → product; there is no redundant product FK. Snapshot columns support historical purchase details, but SQL does not populate or make those snapshots immutable.

CHECK constraints validate boolean flags, size codes, nonnegative prices/stock/amounts, positive purchased quantities and `total_amount = item_subtotal + shipping_charge`. IDs and stock use unsigned integer types; money uses decimal types in SGD. Row constraints cannot validate an order subtotal against all its items, require a nonempty order or handle concurrent checkout. Those checks are not implemented by the current bag.

## Seeded catalogue

Colour IDs match image numbers; parent product IDs are intentionally nonconsecutive. Images 6/7, 8/9 and 13/14 share parent products. Each colourway below has XS, S, M and L variants.

| Colour/image ID | Parent ID / product | Colour | Swatch | Image path | SGD price |
| ---: | --- | --- | --- | --- | ---: |
| 1 | 1 / Cloud Check Top | Midnight Navy | `#222532` | `images/newin1.jpg` | 42.00 |
| 2 | 2 / Sunday Midi Skirt | Navy | `#2F3D5A` | `images/newin2.jpg` | 48.00 |
| 3 | 3 / Kiara Skirt | Charcoal | `#4D4B54` | `images/newin3.jpg` | 45.00 |
| 4 | 4 / Everyday Mini | Chocolate Brown | `#604332` | `images/newin4.jpg` | 45.00 |
| 5 | 5 / Sallie Skirt | Charcoal | `#46454A` | `images/newin5.png` | 56.90 |
| 6 | 7 / Pleated Mini Skirt | Dusty Blue | `#8DA0B2` | `images/newin6.jpg` | **48.00 demo** |
| 7 | 7 / Pleated Mini Skirt | Dusty Mauve | `#9A7A94` | `images/newin7.jpg` | 48.00 |
| 8 | 8 / Ruffle Top | Midnight | `#3D3C40` | `images/newin8.jpg` | 46.00 |
| 9 | 8 / Ruffle Top | Ivory | `#F1EFE8` | `images/newin9.jpg` | 44.00 |
| 10 | 10 / Ivory Lace Corset Top | Ivory | `#EDEBE5` | `images/newin10.jpg` | 46.00 |
| 11 | 11 / Ivory Lace Mini Dress | Ivory | `#EEE9DF` | `images/newin11.jpg` | 62.00 |
| 12 | 12 / Butter Yellow Sundress | Butter Yellow | `#F6E9AB` | `images/newin12.jpg` | 58.00 |
| 13 | 13 / Sundress | Blush Pink | `#EECFD0` | `images/newin13.jpg` | 58.00 |
| 14 | 13 / Sundress | Powder Blue | `#D1E7EA` | `images/newin14.jpg` | 58.00 |
| 15 | 15 / Ivory Ruffle Sundress | Ivory | `#F2EBDD` | `images/newin15.jpg` | 62.00 |
| 16 | 16 / Charcoal Wide-Leg Trousers | Charcoal | `#555458` | `images/newin16.jpg` | 54.00 |

All 16 image paths exist in this checkout. Images 8/9 deliberately retain different prices; the application uses the selected colourway's price.

Catalogue caveats:

- Image 6's SGD 48.00 is a temporary demonstration price copied from image 7, not a verified supplier price.
- Product 7 retains a mauve-specific description, which must not be treated as verified copy for Dusty Blue. Products 8 and 13 combine descriptions from their colourways; shared names and applicable description details still require catalogue review.
- Colour labels and swatches are the approved preview values recorded in the current seed. Product-specific fit information and size guidance remain NULL; the size labels are not measured fit guarantees.
- The seed records a source export dated 30 October 2026 09:00. That export is not present in this checkout, so the current documentation does not claim an independent comparison against it.

## Demonstration stock and counts

| Table | Expected rows after a successful fresh schema + seed import |
| --- | ---: |
| `products` | 13 |
| `product_colours` | 16 |
| `size_guide` | 4 |
| `product_variants` | 64 |
| `customers` | 0 |
| `orders` | 0 |
| `order_items` | 0 |

There are seven tables. Three products have two colourways and ten have one. Every colourway has four sizes (XS, S, M, L) with **5 demonstration units per variant**: 20 per colourway and **320 units total**. These are mock allocations, not real inventory.

Variant IDs are seeded as `(colour_id - 1) * 4 + sort_order` (1–64). Application code queries stored IDs rather than calculating them. Only `product_variants.stock_quantity` stores stock. Editing the seed file does not update an installed database.

## Implemented application functionality

| Files | Current behaviour |
| --- | --- |
| `php/db.php` | MySQLi connection to `chwoo_db` using `utf8mb4` and exception reporting. |
| `index.php`, `php/newin.php` | Database-driven active product listings using each product's default-colour image and price; New In paginates products. |
| `php/shop.php` | Product filtering/sorting and default-colour cards; stock availability is aggregated across each product's colour/size variants. |
| `php/product.php` | Product-specific colour images, swatches and prices; XS/S/M/L selection and database size guidance. Add-to-bag validates the exact active product/colour/size and checks its variant stock against the quantity already in the session bag. |
| `php/bag.php` | Shared bag data and controls. Reads current names, sizes, images, prices and stock from variant/colour/product joins; removes obsolete or invalid entries and flags unavailable quantities. |
| `php/bag_action.php` | CSRF-protected increase/decrease/remove operations. Increases recheck the exact active variant's current stock. |
| `php/cart.php`, `php/bag_drawer.php` | Full bag and drawer using the same shared session bag and totals. |

`$_SESSION['chwoo_cart']` is keyed by `variant_id`; each entry stores `variant_id` and `quantity`. Display details and prices are retrieved from the database. The bag does not reserve or decrement database stock, and quantities can become unavailable after another stock change.

Demonstration delivery is **SGD 5 for a nonempty bag below SGD 80**, and free from SGD 80. An empty bag has no delivery charge. This rule is implemented in `chwooDeliveryCharge()` in `php/bag.php`.

Authentication, checkout, payment processing and database order recording are not implemented. The `customers`, `orders` and `order_items` tables provide supporting structure only. The checkout control currently shows a pending message without placing an order; wishlist actions remember a sign-in intent rather than saving an authenticated wishlist. No card data should be stored.

## Verification boundaries

Static inspection confirms that the schema, seed counts, colour/swatches and existing PHP query column names agree. This does not establish the state of an installed database or prove live SQL parsing, constraint enforcement or end-to-end website behaviour. No database import or live database modification was performed during Task 03E.

After an intentional fresh installation, these read-only queries can check the expected counts and total demonstration stock:

```sql
SELECT 'products' AS table_name, COUNT(*) AS row_count FROM products
UNION ALL SELECT 'product_colours', COUNT(*) FROM product_colours
UNION ALL SELECT 'size_guide', COUNT(*) FROM size_guide
UNION ALL SELECT 'product_variants', COUNT(*) FROM product_variants
UNION ALL SELECT 'customers', COUNT(*) FROM customers
UNION ALL SELECT 'orders', COUNT(*) FROM orders
UNION ALL SELECT 'order_items', COUNT(*) FROM order_items;

SELECT SUM(stock_quantity) AS total_demo_units FROM product_variants;

-- Expected: no rows (four variants and 20 demo units per colourway).
SELECT c.colour_id, COUNT(v.variant_id) AS variants,
       COALESCE(SUM(v.stock_quantity), 0) AS stock
FROM product_colours AS c
LEFT JOIN product_variants AS v ON v.colour_id = c.colour_id
GROUP BY c.colour_id
HAVING COUNT(v.variant_id) <> 4 OR COALESCE(SUM(v.stock_quantity), 0) <> 20;
```

Actual import success, local engine constraint enforcement and database-backed website tests remain to be verified. These expected stock totals apply to the fresh demonstration seed, before any inventory changes.
