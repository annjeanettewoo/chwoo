# CHWOO database — Task 02B, size-specific inventory amendment

## Scope and installation

Only `database/schema.sql`, `database/seed_products.sql` and this document are changed by this task. No PHP/frontend work, database import/reset, commit or push is performed. Existing unrelated working-tree changes are preserved.

The amendment supersedes the earlier six-table, colour-level-stock proposal. The fresh-install design has **seven tables, 13 products, 16 colours, 64 size-specific variants and four shared sizes**. These files are fresh-install definitions, not migrations. Do not import over the previous schema or source export. There is no reset script, destructive statement, `INSERT IGNORE` or conflict-hiding upsert.

The existing Task 02 seed and documentation identify the catalogue source as the phpMyAdmin export `chwoo_db.sql`, dated 30 October 2026 09:00, MariaDB 10.4.32 (recorded SHA-256 `c9ce6340649b49c89bc19c0787a0a925cf35bdc6535fb9dca10522b86d9abdc9`). That export is not present in this checkout; this amendment preserves known values from the existing seed rather than claiming a new comparison against the export. It had 15 rows: IDs 1–5 and 7–16. The confirmed brief adds existing image 6 and consolidates images 6/7, 8/9 and 13/14; there are no additional garments or colour options.

For a future separately authorised installation, back up any existing database and verify the backup first. Use a fresh empty `chwoo_db` in an isolated/local XAMPP MariaDB 10.4.32 instance. Import `schema.sql` first, then `seed_products.sql`, and inspect every statement for errors/warnings plus all counts below. Database credentials remain in `php/db.php`. Both team members must use the same files/revision. Do not also import the old seed/export. Nothing is imported in this task.

`CREATE DATABASE IF NOT EXISTS` allows an empty database; plain `CREATE TABLE` deliberately fails if tables exist. DDL is not transactional and import clients can continue after errors: a final COMMIT or completion banner does not prove success. Stop on failure and inspect state before retrying. Use strict SQL mode. The target is MariaDB 10.4.32; the default-colour constraint uses MariaDB's `PERSISTENT` generated-column syntax. Live engine acceptance has not been tested here.

## Tables and integrity

| Table | Structure and role |
| --- | --- |
| `products` | Unique garment: product ID, generic name, category, description, optional fit information, active flag. No price, image or inventory column. |
| `product_colours` | Colour ID, parent product FK, colour name, single unique image path, price, default flag. Unique `(product_id, colour_name)`. |
| `product_variants` | Variant ID, colour FK, shared `size_code` FK, stock quantity. Unique `(colour_id, size_code)`; the sole inventory authority. No duplicated image or price. |
| `size_guide` | `size_code` PK restricted to XS/S/M/L, optional guidance and display order. |
| `customers` | Account ID, full name, unique email, password hash, creation timestamp. No demonstration accounts. |
| `orders` | Customer FK, recipient/contact/delivery snapshots, item subtotal, shipping, total, payment/order states, timestamps and acknowledgement timestamp. |
| `order_items` | Order FK, exact purchased `variant_id` FK, positive quantity, unit price, purchased product-name/size/colour snapshots. Product identity follows variant → colour → product. |

A product has colours, a colour has four size variants, and each variant reuses a shared size code. The cross join in the seed creates every combination exactly once. Uniqueness and FKs enforce valid, nonduplicate combinations; they do not require four rows per future colour automatically. Task 03 catalogue/inventory writes must maintain completeness transactionally.

A generated `default_product_id` is the parent ID only for default colours, otherwise NULL. Its unique index permits at most one default colour per product while allowing multiple nondefaults. All seeded products have exactly one default; SQL does not enforce at least one default after arbitrary future edits. Change defaults in a transaction, clearing the old default first. The demonstration defaults are the representative source images (7, 8 and 13 for the merged products).

All FKs use restrictive default delete/update behaviour, protecting historical purchases. Deactivate products instead of deleting referenced records. Never repurpose an existing variant's colour/size or a purchased colour's parent; retain identities and add new records when needed. Checkout must populate historical snapshots from trusted database data and preserve them; SQL does not automatically fill or make snapshots immutable. There is no redundant order-item product ID that could disagree with the purchased variant.

CHECK constraints validate boolean flags, size codes, nonnegative prices/stock/amounts, positive purchased quantities and `total_amount = item_subtotal + shipping_charge`. Unsigned types provide an additional inventory bound. Row constraints cannot enforce order subtotals against all item rows, a nonempty order or safe concurrent checkout. PHP must enforce these transactionally and reject database warnings/errors.

All money is SGD. No tax/discount or shipping price is invented. Payment states remain Pending/Paid/Failed; order states remain Pending/Confirmed/Cancelled. Successful simulated checkout is Paid/Confirmed after editable review. Customer/contact snapshots, shipping breakdown and acknowledgement tracking are retained. Do not store card data. Future connections should consistently use UTC timestamps. No post-purchase editing or approval workflow is added.

## Catalogue mapping and preserved information

Colour IDs match image numbers; parent IDs preserve a representative source product ID and are deliberately nonconsecutive. Images 9 and 14 are now colours of parents 8 and 13, not independent products. Every row below has XS, S, M and L for each listed colour.

| Parent ID | Parent product | Colour ID / label / image path / SGD price |
| --- | --- | --- |
| 1 | Cloud Check Top | 1 / unspecified / `images/newin1.jpg` / 42.00 |
| 2 | Sunday Midi Skirt | 2 / unspecified / `images/newin2.jpg` / 48.00 |
| 3 | Kiara Skirt | 3 / unspecified / `images/newin3.jpg` / 45.00 |
| 4 | Everyday Mini | 4 / unspecified / `images/newin4.jpg` / 45.00 |
| 5 | Sallie Skirt | 5 / unspecified / `images/newin5.png` / 56.90 |
| 7 | Pleated Mini Skirt | 6 / unspecified / `images/newin6.jpg` / **48.00 demo**; 7 / Mauve / `images/newin7.jpg` / 48.00 |
| 8 | Ruffle Top | 8 / Midnight / `images/newin8.jpg` / **46.00**; 9 / Ivory / `images/newin9.jpg` / **44.00** |
| 10 | Ivory Lace Corset Top | 10 / Ivory / `images/newin10.jpg` / 46.00 |
| 11 | Ivory Lace Mini Dress | 11 / Ivory / `images/newin11.jpg` / 62.00 |
| 12 | Butter Yellow Sundress | 12 / Butter Yellow / `images/newin12.jpg` / 58.00 |
| 13 | Sundress | 13 / Blush Pink / `images/newin13.jpg` / 58.00; 14 / Powder Blue / `images/newin14.jpg` / 58.00 |
| 15 | Ivory Ruffle Sundress | 15 / Ivory / `images/newin15.jpg` / 62.00 |
| 16 | Charcoal Wide-Leg Trousers | 16 / Charcoal / `images/newin16.jpg` / 54.00 |

Known image paths, all 15 known prices, categories, active flags and description text are preserved. Merged parent descriptions concatenate the original sentences for images 8/9 and 13/14 without discarding either. Parent 7 retains image 7's original mauve-specific description, which must not be read as verified information about image 6. Product-specific fit information remains NULL. Parent category totals are 4 Tops, 5 Bottoms, 4 Dresses.

Teammate confirmation still needed:

- Image 6 has no source product row. Its colour label is blank (unspecified); **48.00 is a temporary demonstration price copied from image 7**, not a verified price. Confirm its real colour, price and applicable description before treating them as catalogue facts.
- Images 1–5 have no established colour labels. Blank `colour_name` is an explicit unspecified label, not a new named colour; do not infer their colours from images or reuse the hardcoded PHP swatches. Other labels are extracted from their existing product names and still need final marketing approval.
- Merged names are neutral working names. Original names were image 7 **Mauve Pleated Mini Skirt**, image 8 **Midnight Ruffle Top**, image 9 **Ivory Floral Ruffle Top**, image 13 **Blush Pink Sundress**, image 14 **Powder Blue Sundress**. Confirm final shared names and whether floral/ruffle, relaxed/flowing description differences apply to both colours. No new final marketing copy is invented.
- `images/newin1.jpg` is missing locally. Preserve this known path; supply the correct asset separately. The other 15 colour paths, including image 6, exist locally.

## Demonstration stock

Every colour receives four variants with **5 demonstration units per size**. This is 20 per colour, 40 per two-colour product, 20 per single-colour product and **320 units total**. The figures are mock allocations, not real inventory and not a claim to preserve aggregate source stock after the catalogue correction. Shared sizes are demonstration labels with NULL guidance, not measured fit guarantees.

Variant IDs are seeded deterministically as `(colour_id - 1) * 4 + sort_order` (1–64); application code must query stored IDs rather than calculate them. Only `product_variants.stock_quantity` stores stock. Changing a seed does not update an installed database.

## Task 03: exact PHP dependencies and query changes

Current PHP is intentionally unchanged and is incompatible with the relocated price/image/stock columns until Task 03. No compatibility views or duplicate counters are introduced.

| Existing dependency | Required change |
| --- | --- |
| `index.php`, homepage SELECT of product ID/name/price/image, limit 4 | Join `products p` to `product_colours c ON c.product_id = p.product_id AND c.is_default = 1`; select `c.price`, `c.image_path`. Keep active filter and parent ID order/limit. |
| `php/newin.php`, count and paginated SELECT | Count active parents as now; list parents joined only to default colours for price/image. Keep pagination on 13 parents, not 16 colours or 64 variants. Qualify `p.product_id DESC`; it remains a heuristic, not a date. |
| `php/shop.php`, SELECT/filter/sort and sold-out display | Use the default-colour join for card image/price. Derive product stock by joining colours/variants and aggregating per parent in a subquery; do not duplicate cards by joining raw variants. Qualify category/name/ID sort expressions and use `c.price` for displayed-default-price sorting. A card price is the default colour's price; selected-colour price may differ. |
| `php/product.php`, product SELECT and hardcoded size/colour arrays | Load the active parent and all colours/variants/shared sizes with the join below. Use each colour's image/price and its size-specific stock. Resolve a submitted variant ID (or colour ID plus size) against that active parent. Reject combinations not returned. Render database labels/guidance; blank colour means unspecified, never invented named swatches. |
| `php/product.php`, POST stock checks and cart session key | Replace product-wide stock and summing every product line with checks against the exact variant. Key `chwoo_cart` by `variant_id`; store `variant_id` and `quantity`. Derive product ID, colour ID, size and labels from MySQL, even if cached for display. Existing keys are `productId:size:colour-with-hyphens` with `product_id`, `size`, `colour`, `quantity`; invalidate/revalidate old sessions rather than silently mapping fictitious colours. Wishlist IDs remain parent IDs; validate stale IDs 9/14 or explicitly map them to parents 8/13. |
| `php/cart.php`, product-only prepared SELECT and session loop | Query by variant ID with the four-table join below; display that colour's price/image, parent name and exact size. Validate parent activity, positive quantity and current variant stock. Removal `item_key` must refer to the new session key; quantity edits must validate the same variant. Recalculate totals from database prices. |
| Future checkout/order reads and writes (none exist yet) | Insert `order_items.variant_id`, quantity, unit price and trusted name/size/colour snapshots. There is no `order_items.product_id`; use variant → colour → product when current identity is needed. Use saved snapshots for historical receipts. |

Product options query (bind the active parent ID):

```sql
SELECT p.product_id, p.product_name, p.description, p.fit_information,
       c.colour_id, c.colour_name, c.image_path, c.price, c.is_default,
       v.variant_id, v.size_code, v.stock_quantity, s.guidance
FROM products AS p
JOIN product_colours AS c ON c.product_id = p.product_id
JOIN product_variants AS v ON v.colour_id = c.colour_id
JOIN size_guide AS s ON s.size_code = v.size_code
WHERE p.product_id = ? AND p.is_active = 1
ORDER BY c.is_default DESC, c.colour_id, s.sort_order;
```

For cart/checkout use the same joins with `WHERE v.variant_id = ? AND p.is_active = 1` instead. For card stock, left join this grouped result by parent, using `COALESCE(stock_quantity, 0)`:

```sql
SELECT c.product_id, SUM(v.stock_quantity) AS stock_quantity
FROM product_colours AS c
JOIN product_variants AS v ON v.colour_id = c.colour_id
GROUP BY c.product_id;
```

Checkout must revalidate submitted variants/prices, use exact decimal/cents arithmetic and review edits before simulated payment. In a single transaction lock variant rows in a consistent order, then conditionally decrement each selected variant and require exactly one affected row:

```sql
UPDATE product_variants
SET stock_quantity = stock_quantity - ?
WHERE variant_id = ? AND stock_quantity >= ?;
```

Bind requested quantity to both quantity placeholders. Insert the order and snapshots, verify item totals, commit on complete success and roll back on failure. Guard duplicate submissions. Email follows commit; a retry must not repeat payment or inventory changes. Registration still requires PHP `password_hash()` / `password_verify()`. These are Task 03 requirements, not functionality implemented here.

## Verification

Expected fresh-install counts (no live import performed):

| Table | Rows |
| --- | ---: |
| products | 13 |
| product_colours | 16 |
| product_variants | 64 |
| size_guide | 4 |
| customers | 0 |
| orders | 0 |
| order_items | 0 |

Three parents have two colours; ten have one. Each colour has four variants and 20 demonstration units. All parents have exactly one seeded default.

Static checks passed for the seed literals and cross-join expansion for counts, exact pairs, valid parents, unique colour/size selections and variant IDs, known price/path preservation, default counts, shared sizes, image presence and 320 mock units. Schema checks passed for seven PKs, six compatible FK targets, selection/default uniqueness and sole inventory/image placement. `git diff --check` passed. These are source checks, not live SQL parsing, constraint execution or end-to-end PHP tests.

Optional read-only checks after a future authorised import:

```sql
SELECT 'products' AS table_name, COUNT(*) AS row_count FROM products
UNION ALL SELECT 'product_colours', COUNT(*) FROM product_colours
UNION ALL SELECT 'product_variants', COUNT(*) FROM product_variants
UNION ALL SELECT 'size_guide', COUNT(*) FROM size_guide;

-- Expected: no rows (every colour has four sizes and 20 demo units).
SELECT c.colour_id, COUNT(v.variant_id) AS variants,
       SUM(v.stock_quantity) AS stock
FROM product_colours AS c
LEFT JOIN product_variants AS v ON v.colour_id = c.colour_id
GROUP BY c.colour_id
HAVING COUNT(v.variant_id) <> 4 OR SUM(v.stock_quantity) <> 20;

-- Expected: 13 rows, ten colour_count=1 and three colour_count=2; defaults=1.
SELECT p.product_id, COUNT(c.colour_id) AS colour_count,
       SUM(c.is_default) AS defaults
FROM products AS p
LEFT JOIN product_colours AS c ON c.product_id = p.product_id
GROUP BY p.product_id ORDER BY p.product_id;
```

On a separately authorised disposable MariaDB instance, inspect `SHOW CREATE TABLE` for all seven tables and test rejection of duplicate colour/size pairs, duplicate default colours, invalid FKs, invalid sizes, negative stock/prices, zero purchase quantities and inconsistent order totals. Verify restrictive deletion and transactional rollback/concurrent depletion once checkout exists. None of those live tests was run in this task.
