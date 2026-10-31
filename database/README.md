# CHWOO database

CHWOO uses PHP/MySQLi with the `chwoo_db` database. The latest database has nine tables, including optional customer-owned saved addresses, with prices and images stored per colourway, inventory stored per colour/size variant, and product-level wishlists linked to customers. `schema.sql` defines the complete structure; `seed_products.sql` is the authoritative source for the demonstration catalogue. No migration or previous SQL patch is needed for a fresh installation.

## Task 06A.1: payment handoff and saved addresses

Payment is assumed to be handled by a third-party provider under the IE4727 guidelines. No portal, redirect, gateway, card fields, card validation or payment-method storage is implemented. **CONFIRM ORDER** is a CSRF-protected POST representing a successful simulated provider response. It invokes the existing transaction, stock locks and unique-token workflow. The UI's `Paid` status is a local saved status, not evidence of external financial settlement.

The unsuccessful-provider test action is accepted only when the server process has `CHWOO_TEST_PAYMENT_FAILURE=1` AND `CHWOO_DB_NAME` matches the generated `chwoo_task06_test_` plus 12 hexadecimal characters. Both conditions are required. The test runner sets/restores the flag for its disposable environment; it does not change normal purchases. There is no visible failure button, and URL parameters cannot enable the test mode. Other invalid requests and transaction errors retain their existing checks and preserve the bag.

`customer_addresses` is the ninth canonical table, introduced in Task 06A.1. It has an unsigned auto-increment `id` primary key; a customer FK with delete cascade; recipient name/email/phone, address text, six-character postal code, optional notes, creation timestamp and a 64-character deduplication fingerprint. Unique `(customer_id, fingerprint)` prevents repeated saves of the same recipient/contact/address. Deduplication normalises case and whitespace and excludes email/notes; saving again does not overwrite an existing record. The normal product seed is unchanged and contains no addresses or test customers.

The save checkbox defaults to unchecked. Saved address selection uses an ownership-scoped, CSRF-protected POST to prefill the editable delivery form; the customer must still continue through validation and review. One-off changes do not overwrite saved addresses. Removal uses an ownership-scoped prepared DELETE and CSRF validation. Orders retain independent delivery snapshots after address deletion. If the new table has not been installed, ordinary checkout still works with a new address; opting to save reports an error and can be retried with the option unchecked.

### Manual local database setup

For Windows XAMPP, Apache port 8000, the required `C:\xampp\htdocs\chwoo` location and optional Mercury/Thunderbird setup, follow the [root installation guide](../README.md#windows-xampp-installation). For a fresh installation, use an absent or intentionally empty `chwoo_db`, import the complete latest `database/schema.sql`, then `database/seed_products.sql` through phpMyAdmin. Verify nine InnoDB tables, 13 products, 16 colours, four size labels, 64 variants and zero seeded addresses/customers/wishlists/orders/items. Do not import the full files over a populated store or delete installed records.

To retain the installed store instead: back it up, open **http://localhost:8000/phpmyadmin/ → chwoo_db**, verify `customers.customer_id` is an unsigned INT primary key on InnoDB, and confirm `customer_addresses` is absent. Open **SQL**, copy only the complete `CREATE TABLE customer_addresses (...) ENGINE=InnoDB ...;` statement from the latest canonical `schema.sql`, and execute it once. Do not run the surrounding database, customer, catalogue or order statements. Verify **customer_addresses → Structure** against the canonical definition: primary key, unique `uq_customer_address`, FK, defaults and utf8mb4 collation. If the table already exists, inspect rather than rerunning CREATE. This is a manual setup instruction, not a migration or local update script. No existing order-processing definitions change.

### Acknowledgement email and verification

Customer wording thanks the customer and confirms the recorded order; it makes no bank-settlement or external-email-delivery claim. The normal confirmation page no longer contains the technical email preview, for any acknowledgement status. Prepared/Accepted/Failed and the acceptance timestamp remain internal diagnostics. The HTML and plain-text alternatives use saved order snapshots and link to the account's order history. See the root `README.md` for the gitignored local override and verified Mercury settings. `delivery_verified` is local manual-verification metadata only; it does not configure SMTP or prove delivery of individual messages.

Run `C:\xampp\php\php.exe -d disable_functions=mail tests\task06.php` for the order and address integration suite, which also runs Task 05. Its test database/server are disposable; the installed database is checked for unchanged records. Run `node tests/checkout-validation.mjs` and `node tests/notifications.mjs` separately. No card-form or saved-payment-method files/tables are required.

## Task 06 setup and order fields

Historically, Task 06 added the following definitions to its eight-table baseline; Task 06A.1 then added `customer_addresses`. The current complete schema has nine tables and includes all these definitions. The general product seed contains no customer or order fixtures.

| Table / definition | Purpose |
| --- | --- |
| `orders.checkout_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL` | Server-generated checkout identifier. Unique index `uq_orders_checkout_token (checkout_token)` prevents a successful submission being recorded twice. Nullable for legacy orders; new purchases always supply a 64-character hexadecimal token. |
| `orders.acknowledgement_status ENUM('Prepared','Accepted','Failed') NOT NULL DEFAULT 'Prepared'` | Distinguishes prepared email text, local transport acceptance and attempted/invalid-config failure. `Accepted` does not establish mailbox delivery. |
| `order_items.product_image_path VARCHAR(255) NOT NULL DEFAULT ''` | Snapshot of the selected colourway's image path, alongside existing name/colour/size/price snapshots. A removed image displays a placeholder. |

The existing `acknowledgement_sent_at` timestamp is populated only when the local PHP mail transport accepts a message. It records acceptance time, not verified delivery. Postal code and optional delivery notes are saved in the existing `orders.delivery_address` text; no duplicate address fields are needed.

**An earlier Task 06 note described fields missing from the installation at that time; it is not a current inventory of the installed store.** Inspect an existing installation against `schema.sql` before deciding whether anything is missing. A fresh schema-then-seed import already includes the three fields and unique index above. Never import the full files over populated tables.

If the developer needs to retain an existing installation, first back it up and inspect its current structure. In phpMyAdmin select **chwoo_db → orders → Structure**, add `checkout_token` as nullable CHAR(64), default NULL, `ascii_bin` collation; add its UNIQUE index named `uq_orders_checkout_token`. Add `acknowledgement_status` as ENUM with values `'Prepared','Accepted','Failed'`, NOT NULL, default `Prepared`. Then select **order_items → Structure** and add `product_image_path` as VARCHAR(255), NOT NULL, default the empty string. Verify types, nullability, defaults, collation and index name against `schema.sql`; skip definitions that already match. These are optional manual Structure operations, not an application migration or an automatically executed update script. Existing orders keep NULL tokens and empty image paths. The preferred reproducible setup remains a fresh schema-then-seed import.

New orders are linked to the session customer, recorded as `Paid` / `Confirmed` **for simulated payment only**, and written with their item snapshots and stock deductions in one InnoDB transaction. Row locks and guarded stock UPDATEs prevent overselling. Integer-cent calculations are shared with the bag; successful retries resolve to the order with the same unique token. Failed simulations create no order and deduct no stock.

### Local acknowledgement setup

`php/mail_config.php` defaults to delivery disabled, empty sender/recipient and `site_url=http://localhost:8000/chwoo`. An optional gitignored `php/mail_config.local.php` overrides those defaults; the tracked file must remain safe for other developers. No database schema change is required for the HTML email.

Before enabling delivery, the developer must provision and authorise their own mailbox on the permitted **local** mail server. Set the fixed `recipient`, `sender` and `enabled=true` only in `mail_config.local.php`. The customer's checkout email is never a delivery destination. External recipient domains are rejected; no email API, external service or Composer library is used. The app only permits PHP's loopback SMTP transport with an empty `sendmail_path`; it does not modify system-wide mail configuration. Enabling the app setting alone does not install or configure a mail server.

**Historical manual evidence, not rerun for Task 08B:** verification was recorded on **31 October 2026**: Mercury SMTP on localhost:25 and POP3 on localhost:110 worked locally, and PHP `mail()` successfully sent through Mercury using sender `chwoo-store@localhost`. A real CHWOO purchase, **Order #5**, generated the branded HTML acknowledgement, which was received and opened in Thunderbird at the authorised `chwoo-inbox@localhost` mailbox. The email contained the correct purchased item, pricing and order details. Refreshing the confirmation page did not generate a duplicate email. Automated regression tests were also reported as passed; those tests use disposable fixtures and mock transports and send no real mail.

This verifies delivery in the configured local environment only, not delivery to external customer email addresses or another machine. For another machine, follow the Mercury, Thunderbird and gitignored local-override setup instructions in the root `README.md`, then verify its local inbox separately. Enabled settings belong only in the ignored override. A mail failure occurs after the purchase commits and cannot roll back the order. Confirmation refreshes and duplicate purchase requests never resend mail automatically.

### Task 06 verification

Run `C:\xampp\php\php.exe -d disable_functions=mail tests\task06.php` with local MariaDB available. The CLI-only runner creates a randomly named `chwoo_task06_test_*` database, imports the canonical schema then seed there, starts an isolated loopback PHP server with the process-only `CHWOO_DB_NAME` override, and runs HTTP, rollback and concurrent-transaction checks. It removes only its generated test database/server and verifies that installed `chwoo_db` records are unchanged. It does not rebuild or change the live database. Disposable customers/orders stay out of the general seed. Run suites sequentially without concurrent store activity; see the root README for Task 07C and temporary-directory setup.

Run `C:\xampp\php\php.exe -d disable_functions=mail tests\order_mail.php` for focused mock-only email tests. Run `node tests\checkout-validation.mjs`, `node tests\checkout-history.mjs` and `node tests\notifications.mjs` for dependency-free JavaScript unit checks. These do not substitute for browser or Thunderbird layout verification. See the root `README.md` for current test commands and mail configuration.

## Fresh installation with phpMyAdmin

These scripts are for fresh installations, not incremental migrations. Do not import them over an existing populated database or combine them with an old seed/export. Both team members should use the same SQL files/revision.

**Preserve the installed store. If `chwoo_db` contains any tables or records, do not delete it, overwrite it or run these imports there.** Both SQL files explicitly select `chwoo_db`; choosing another name in phpMyAdmin does not isolate the import. Use a separate empty MariaDB instance for a fresh-install rehearsal, or let the supplied integration runners redirect the scripts into their generated disposable database.

1. Preserve important existing database records and verify any backup separately. These steps do not migrate an existing store.
2. Confirm `chwoo_db` is absent or intentionally empty. If it contains tables, stop and use an isolated empty instance; no deletion is required by this guide.
3. In phpMyAdmin, import `database/schema.sql`. It creates/selects `chwoo_db` and creates the tables.
4. Import `database/seed_products.sql` into the same MariaDB instance.
5. Inspect both imports for errors/warnings and verify the nine tables and record counts below. A completion banner alone does not prove success.
6. With Apache and MySQL running in Windows XAMPP, open `http://localhost:8000/chwoo/`. Check catalogue pages and LOOKBOOK (the Outfit Builder). On the disposable demonstration installation, register fictitious customers, then test login, wishlist, saved addresses, product colour/size selection, stock validation, bag updates, delivery/review and simulated payment/confirmation/order history. Purchases deduct stock, so do not use installed store records for mutation checks. Fresh installations require only schema then product seed; accounts are created by registration. The corresponding local phpMyAdmin URL is `http://localhost:8000/phpmyadmin/`.

Connection settings are in `php/db.php`, which selects `chwoo_db` and sets `utf8mb4`. Check these settings against the local installation.

Repeated imports are not safe: `CREATE DATABASE IF NOT EXISTS` permits an empty database, but plain `CREATE TABLE` fails when tables already exist, and repeated seed inserts encounter duplicate primary/unique keys. DDL is not transactional, and an import client can continue after errors. Stop on failure and inspect database state before retrying; a final `COMMIT` does not prove that every statement succeeded. Use strict SQL mode when importing.

The local server is XAMPP MariaDB 10.4.32. The schema uses InnoDB, `utf8mb4_unicode_ci`, CHECK constraints and MariaDB's `PERSISTENT` generated-column syntax. Task 06 successfully tested a fresh schema/seed import in a disposable database on that server; the installed store was left unchanged. Compatibility with a different MySQL/MariaDB version must be verified before importing.

## Optional existing-database installation for Task 04C

The preferred workflow is the reviewed fresh import above. To keep the currently installed accounts/catalogue instead, select **chwoo_db → SQL** in phpMyAdmin and execute the following statement once. Do not import the full schema or seed over the existing tables. First verify that `customers.customer_id` and `products.product_id` are `INT UNSIGNED` primary keys on InnoDB tables, and that no `wishlist` table exists. If one already exists, inspect its structure instead of rerunning this statement.

```sql
CREATE TABLE wishlist (
    wishlist_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (wishlist_id),
    UNIQUE KEY uq_wishlist_customer_product (customer_id, product_id),
    KEY idx_wishlist_product (product_id),
    CONSTRAINT fk_wishlist_customer FOREIGN KEY (customer_id)
        REFERENCES customers (customer_id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id)
        REFERENCES products (product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Inspect the result for errors, then use **wishlist → Structure** to verify its primary key, unique customer/product index, product index and both foreign keys. The new table starts with zero rows and does not alter other tables or records. No local update SQL script or migrations directory is required. Database installation is left to the user; the application does not create tables automatically.

## Tables and integrity

| Table | Primary key | Data and relationships |
| --- | --- | --- |
| `products` | `product_id` | Garment name, category, description, optional fit information and active flag. No price, image or inventory column. |
| `size_guide` | `size_code` | Shared XS/S/M/L labels, optional guidance and display order. |
| `product_colours` | `colour_id` | `product_id` FK to `products`; colour name, `swatch_hex`, unique image path, decimal price and default flag. Unique `(product_id, colour_name)`. |
| `product_variants` | `variant_id` | `colour_id` FK to `product_colours`, `size_code` FK to `size_guide`, and stock quantity. Unique `(colour_id, size_code)`; the sole inventory authority. |
| `customers` | `customer_id` | Full name, unique email, password hash and creation timestamp. No demonstration accounts. |
| `customer_addresses` | `id` | Customer FK with delete cascade; recipient/contact/address/postal/notes snapshots, fingerprint and timestamp. Unique `(customer_id, fingerprint)` deduplicates owned saved addresses. |
| `wishlist` | `wishlist_id` | `customer_id` FK to `customers`, `product_id` FK to `products`, and saved timestamp. Unique `(customer_id, product_id)` prevents duplicate saves. Both FKs use `ON DELETE CASCADE` to remove orphan favourites. |
| `orders` | `order_id` | `customer_id` FK to `customers`; unique nullable checkout token; recipient/contact/delivery snapshots, subtotal, shipping, total, simulated payment/order states, acknowledgement status and timestamps. |
| `order_items` | `order_item_id` | `order_id` FK to `orders`, `variant_id` FK to `product_variants`; quantity, unit price and purchased name/image-path/size/colour snapshots. |

`product_colours.swatch_hex` has the exact SQL definition `swatch_hex CHAR(7) DEFAULT NULL`. It supplies the display swatch for a colourway. All seeded values use `#RRGGBB`; SQL does not enforce that format. `php/product.php` validates the format and uses a fallback display colour when necessary. These are preview swatches, not supplier colour specifications.

A product has colourways, and each seeded colourway has four size variants using the shared size codes. The seed's cross join creates every combination exactly once. Foreign keys and uniqueness prevent invalid references and duplicate selections; they do not automatically require four variants for a future colourway.

The generated `default_product_id` unique index permits at most one default colour per product while allowing multiple nondefaults. Each seeded product has exactly one default, but SQL does not enforce at least one after arbitrary edits. Change defaults transactionally, clearing the old default first. Products 7, 8 and 13 use colour IDs 7, 8 and 13 as their defaults.

Foreign keys use restrictive default delete/update behaviour except the wishlist's two delete cascades and the customer-address delete cascade. Deactivate referenced products rather than deleting them, and do not repurpose purchased variant or colour identities. Order-item product identity follows variant → colour → product; there is no redundant product FK. Snapshot columns support historical purchase details, but SQL does not populate or make those snapshots immutable.

CHECK constraints validate boolean flags, size codes, nonnegative prices/stock/amounts, positive purchased quantities and `total_amount = item_subtotal + shipping_charge`. IDs and stock use unsigned integer types; money uses decimal types in SGD. Row constraints cannot validate an order subtotal against all its items, require a nonempty order or handle concurrent checkout. `php/orders.php` performs those checks with prepared statements, integer-cent pricing, a transaction, row locking and atomic stock deductions.

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
| `customer_addresses` | 0 |
| `wishlist` | 0 |
| `orders` | 0 |
| `order_items` | 0 |

There are nine tables. Three products have two colourways and ten have one. Every colourway has four sizes (XS, S, M, L) with **5 demonstration units per variant**: 20 per colourway and **320 units total**. These are mock allocations, not real inventory. The wishlist has no required seed rows and references the existing product IDs. No customer, saved-address, order, order-item or wishlist fixtures are part of the general website seed. These counts describe a successful fresh import, not an installed store after use.

Variant IDs are seeded as `(colour_id - 1) * 4 + sort_order` (1–64). Application code queries stored IDs rather than calculating them. Only `product_variants.stock_quantity` stores stock. Editing the seed file does not update an installed database.

## Implemented application functionality

| Files | Current behaviour |
| --- | --- |
| `php/db.php` | MySQLi connection to `chwoo_db` using `utf8mb4` and exception reporting. |
| `index.php`, `php/newin.php` | Database-driven active product listings using each product's default-colour image and price; New In paginates products. |
| `php/shop.php` | Product filtering/sorting and default-colour cards; stock availability is aggregated across each product's colour/size variants. |
| `php/outfit.php`, `php/outfit_action.php`, `js/outfitBuilder.js`, `css/outfit.css` | LOOKBOOK opens the implemented Top + Bottom / Dress styling board. PHP renders real catalogue identities into HTML; vanilla JavaScript updates the preview and subtotal. A CSRF-protected POST rechecks category, product, colour, exact variant and remaining stock before adding all selected pieces to the existing session bag together. No outfit table, React, JSON endpoint or AJAX is used. |
| `php/product.php` | Product-specific colour images, swatches and prices; XS/S/M/L selection and database size guidance. Add-to-bag validates the exact active product/colour/size and checks its variant stock against the quantity already in the session bag. |
| `php/bag.php` | Shared bag data and controls. Reads current names, sizes, images, prices and stock from variant/colour/product joins; removes obsolete or invalid entries and flags unavailable quantities. |
| `php/bag_action.php` | CSRF-protected increase/decrease/remove operations. Increases recheck the exact active variant's current stock. |
| `php/cart.php`, `php/bag_drawer.php` | Full bag and drawer using the same shared session bag and totals. |
| `php/auth.php`, `php/auth_action.php`, `php/login.php` | Registration with PHP password hashes, login/logout and session identity. Normal authentication opens Account; pending wishlist authentication saves the intended product and returns to the original internal page. Shared return-path allowlist and CSRF token also serve bag/wishlist forms. |
| `php/wishlist.php`, `php/wishlist_action.php` | Prepared wishlist reads/writes scoped to the session customer. POST/Redirect/GET saves/removes products; duplicate saves are idempotent. Guests retain one pending product and validated return path through login or registration. |
| `php/account.php` | Existing account navigation and details/orders, plus real Wishlist cards with current catalogue image/price, product links, removal controls and an empty state. |
| `php/checkout.php`, `php/payment.php`, `php/orders.php` | Delivery details, editable review, CSRF-protected payment simulation, transactional order processing and durable duplicate-submission protection. |
| `php/order_confirmation.php`, `php/order_mail.php` | Owner-only saved confirmation, historical purchase snapshots and preparation/optional local triggering of acknowledgement email. |
| `css/wishlist.css` | Product-detail outline/filled hearts and scoped responsive account cards with neutral text removal controls. Home, Shop and New In have no wishlist controls or wishlist reads. |

`$_SESSION['chwoo_cart']` is keyed by `variant_id`; each entry stores `variant_id` and `quantity`. Display details and prices are retrieved from the database. The bag does not reserve or decrement database stock, and quantities can become unavailable after another stock change.

Demonstration delivery is **SGD 5 for a nonempty bag below SGD 80**, and free from SGD 80. An empty bag has no delivery charge. This rule and integer-cent totals are shared in `php/pricing.php` by the bag, review and order finalisation.

Checkout now leads from delivery details to editable review and demonstration payment. A successful simulated payment creates a confirmed purchase and deducts exact variant stock transactionally; a failed simulation preserves the bag and database. Confirmation and Account order history load saved owner-scoped records. There is no real payment gateway and no card data is collected or stored.

## Wishlist behaviour and security

On the product detail page, an outline heart saves the product and a filled heart removes it. Both use CSRF-protected POST forms and a 303 redirect to the same page, preserving product colour/size selection. Account → Wishlist lists saved products with removal controls that return to that section. Home, Shop, New In and other listing grids do not offer wishlist controls. State is loaded from the database on product/account requests and survives logout and subsequent login; account-specific pages use `Cache-Control: no-store`. Different colourways/sizes of the same product share one heart state.

Guest saves validate the active product before remembering `chwoo_pending_wishlist_product_id` and `chwoo_pending_wishlist_return_to` in the session. Login errors and switching between login/registration retain the intent. Successful authentication regenerates the session ID, saves once (never toggles), consumes the pending intent, and redirects to the validated original path. Ordinary login/registration still opens Account. Logout clears pending intent and authentication, preserving the existing session bag and CSRF convention.

Return destinations pass the same explicit internal-page/query-field allowlist used by bag forms, both when stored and when consumed; external URLs, protocol-relative URLs and arbitrary paths fall back to Shop. Persistent modifications take the customer ID only from the session and verify that the account still exists. Product IDs are bounded unsigned integers; saves require a real active product with a colourway. SQL uses prepared statements and ownership predicates, with foreign keys and a unique index enforcing integrity. No client-supplied customer ID is used. All displayed catalogue/customer text is escaped and image paths use the existing local-image validator. There is no AJAX, fetch or JSON endpoint.

Wishlist cards load current default-colour details (falling back to the first remaining colour when a product has no default); colour/size details and prices are not copied into `wishlist`. Inactive products or products without colours remain removable with a “Currently unavailable” label. Deleted products cascade out of the wishlist when deletion is otherwise permitted by catalogue/order FKs. Out-of-stock active products may still be saved; wishlist saving does not reserve stock. A missing wishlist table/read failure produces an unavailable message and disabled authenticated hearts, rather than a fabricated empty wishlist. Failed writes show a failure notice. Failed pending saves still complete authentication and return to the originating page with an explicit failure notice.

No demo-account SQL fixture is needed: reproducible testing uses fictitious accounts created through registration, with passwords hashed by the existing PHP flow. Do not seed test customers, orders or favourites into `seed_products.sql`.

## Verification boundaries

The following Task 04C / 04C.1 results are historical snapshots, not the current table inventory or a new execution of those checks. The current canonical schema has nine tables. Later regression commands are listed in the root README.

Initial Task 04C checks (historical, before the Task 04C.1 listing-scope correction):

- All 17 PHP files pass XAMPP PHP 8.2.12 syntax checks; all existing JavaScript files pass `node --check`; `git diff --check` passes. New files were also checked for whitespace errors.
- Static checks then confirmed eight canonical table definitions, identical wishlist CREATE TABLE statements in this README and `schema.sql`, and an unchanged `seed_products.sql`.
- Read-only MariaDB checks then confirmed version 10.4.32, seven then-installed InnoDB tables (wishlist absent), unsigned parent keys, and installed counts of 13 products, 16 colours and 64 variants. No database import or persistent database write was performed in that initial check.
- A 26-request local HTTP regression passed against the actual PHP application with a disposable session: Home/Shop/New In/product rendering, no-store headers, POST-only handling, invalid CSRF/ID/action rejection, nonexistent-product rejection, guest product/filter/pagination return intents, failed-login and registration-view intent retention, external return rejection, bag add/increase/decrease/remove, and logout/bag preservation. The test runner removed its disposable session; no customer, order or wishlist rows were created.
- PHP helper checks passed for ID bounds/types, internal return-path/query filtering, escaped outline/filled heart markup, form routing, and guest state. A separate read-only check confirmed missing-table reads report unknown/unavailable state, absent customer validation, and rejection of an absent product before any DELETE.
- Browser checks confirmed catalogue hearts reach sign-in with the save-intent message, selected colour/size display, and a product heart reaches sign-in without requiring a bag size selection. The catalogue and sign-in layouts were visually inspected at the browser's existing viewport. Account cards and other responsive breakpoints have not received browser verification.

Historical Task 04C.1 checks against the installed local wishlist table:

- A 39-request HTTP regression passed using one disposable fictitious customer: product save/remove, correct Account product/image/default-colour price, Account removal and empty state, guest registration and login returns, ordinary login to Account, refresh and new-session login persistence, authenticated CSRF rejection, and absence of listing-page controls/styles on Home, Shop, both New In pages and Collections.
- Drawer increase/decrease/remove passed with updated messages, quantities, shipping, totals and the empty state. Tests verified that the initial response HTML contains an already-open drawer and overlay, no drawer `inert` attribute, `aria-hidden="false"`, and the transition-suppression class. Add-to-bag retains the ordinary `bag=open` flow.
- Browser checks confirmed the polished Wishlist layout and Account removal. All three drawer updates kept it open with the correct contents/totals. Restored state had zero horizontal translation, visible overlay, background scroll lock and focus on Close. Close/manual reopen and Escape were also exercised; the regular 0.3-second transition was restored. The browser's pre-existing bag item was preserved, and only the separate item added for this check was removed.
- The HTTP test customer's account and wishlist rows were removed after testing; no general seed/demo fixture was created. No schema or seed change, import or database rebuild was required for Task 04C.1. Existing application authentication and persistence logic were retained.

Coverage left open at the end of Task 04C.1: two-customer isolation, direct duplicate/FK rejection, inactive/no-colour product handling and product-delete cascades had not received a complete live regression in that task. Account-delete wishlist cleanup was exercised. Its visual checks used the existing browser viewport; other responsive breakpoints were not separately tested then. The drawer uses a full server POST/Redirect/GET reload; this fix bypasses the fresh opening transition on its returned page rather than introducing AJAX.

For the manual demonstration, register fictitious customers using the ordinary form. Browse a filtered Shop or latest-eight New In listing, open a product, then save it there. Verify the original product colour/size URL after authentication or toggling, and check Account → Wishlist. Wishlist is product-level, so product 8's default-colour dashboard price is SGD 46.00 even when colour 9's product page displays SGD 44.00. Remove demonstration favourites using their normal controls. No demo SQL fixture or local-only update SQL script was created.

## Drawer update presentation

Drawer quantity/remove forms keep their existing CSRF-protected PHP POST processing and current stock/totals calculations. Their 303 redirects now append `bag=updated`; full-bag controls keep their original redirects. `bag_drawer.php` renders that response with the drawer/overlay already open and a temporary `is-restoring` class that disables transitions. The existing small vanilla JavaScript initialises focus, inert background and scroll lock immediately, then removes the suppression after the open state has painted. Ordinary BAG clicks and add-to-bag (`bag=open`) still use the normal opening animation. Flash messages and totals remain server-rendered, including when removal empties the bag.

After an intentional fresh installation, these read-only queries can check the expected counts and total demonstration stock:

```sql
SELECT 'products' AS table_name, COUNT(*) AS row_count FROM products
UNION ALL SELECT 'product_colours', COUNT(*) FROM product_colours
UNION ALL SELECT 'size_guide', COUNT(*) FROM size_guide
UNION ALL SELECT 'product_variants', COUNT(*) FROM product_variants
UNION ALL SELECT 'customers', COUNT(*) FROM customers
UNION ALL SELECT 'customer_addresses', COUNT(*) FROM customer_addresses
UNION ALL SELECT 'wishlist', COUNT(*) FROM wishlist
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

The complete fresh schema/seed import was not rerun during Task 04C.1; database-backed website checks used the already-installed local tables. Broader constraint coverage remains as noted above. These expected stock totals apply to the fresh demonstration seed, before any inventory changes.
