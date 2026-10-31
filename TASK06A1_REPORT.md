# Task 06A.1 completion report — revised payment scope

Implemented on `feature/chwoo-orders`, after verifying completed merge `dc53736`, a clean starting tree and no MERGE_HEAD/unresolved paths. No Git mutations were made during this task.

## A. Files changed and purpose

| Files | Purpose |
| --- | --- |
| `php/payment.php` | Customer-facing Payment handoff, actual product/variant summary and total, Proceed to Payment POST, secondary review action, controlled fixture-only failed provider outcome. |
| `php/checkout.php` | Payment wording, optional address save and saved-address selection into the existing editable delivery form. |
| `php/saved_details.php` (new) | Prepared, ownership-scoped address reads, validated saves and duplicate prevention. |
| `php/saved_details_action.php` (new) | Authenticated, CSRF-protected owner-only address removal. |
| `php/account_saved_details.php` (new), `php/account.php` | Account Addresses section with real records/removal; Saved Cards remains an informational placeholder. |
| `js/checkout-validation.js` | Exclude optional save checkbox from delivery field validation. |
| `css/checkout.css`, `css/account.css` | Spaced confirmation information row, saved-address selection and account record styling. |
| `php/order_confirmation.php` | Distinct labelled status/date/payment fields and conditional acknowledgement preview. |
| `php/order_mail.php` | Normal confirmed-order acknowledgement wording. |
| `php/mail_config.php` | Explicit, default-false delivery-verification setting for future preview removal. Delivery remains disabled. |
| `database/schema.sql`, `database/README.md` | One new saved-address table, fresh-install/manual setup instructions and documentation of assumed provider responses. |
| `tests/task05.php`, `tests/task06.php`, `tests/saved_addresses.php` (new) | Updated obsolete payment assertions; retained original regressions and added address/security/snapshot checks. |
| `TASK06A1_REPORT.md` (new) | This completion report. |

No card form, card validation, saved payment-method selection, card database table, gateway or external redirect remains. Draft card-related work was removed after the scope update. `php/orders.php`, `php/pricing.php` and `database/seed_products.sql` are unchanged.

## B. Payment handoff

The page heading is Payment. It shows current selected products, colours/sizes, quantities, line prices and server-calculated subtotal/delivery/total. Proceed to Payment submits the existing success action with CSRF and checkout tokens. The developer-level meaning is an assumed, simulated successful third-party provider response; there is no external financial settlement or redirect. The normal UI avoids repeated demonstration wording and collects no banking credentials.

Return to order review is a separate outlined action. A successful transaction alone redirects to confirmation. Existing invalid-CSRF, changed-price/cart/stock, database and rollback errors remain.

Unsuccessful provider responses are tested by POST action `failure` only when both `CHWOO_TEST_PAYMENT_FAILURE=1` and a generated `chwoo_task06_test_` database name with 12 hexadecimal characters are set in the server process. Both safeguards are required; public URL parameters cannot enable it, and ordinary success submissions are unaffected. The fixture failure produces a persistent helpful message, no order/stock writes and an intact bag, allowing retry/review. No public failure button exists.

## C. Saved delivery addresses

Save this address for next time defaults to unchecked. Valid opt-in saves an address against the session customer. Repeated saves deduplicate a normalised recipient/contact/address/postal identity without overwriting existing entries. Email/notes do not create duplicates of an otherwise identical address.

Saved address selection is a CSRF-protected POST and reads only the authenticated customer's record. It prefills editable fields; the customer still submits delivery details and reviews the order. One-off edits do not overwrite saved records. Account → Addresses lists recipient, phone, address and postal code, with CSRF-protected owner-only removal. Empty sections do not invent records.

Orders retain independent delivery snapshots after address removal. Missing-table reads fall back to no saved selections so new-address checkout remains usable before setup; opting to save reports an actionable error if installation is incomplete.

## D. Order confirmation

Order status, Order date and Payment status now occupy three labelled, spaced fields, with uppercase AM/PM. Values come from the saved order; Paid is shown without `(simulated)`. The repeated demonstration-payment sentence is removed. Existing purchased items, prices, totals, delivery details and ownership checks remain.

## E. Acknowledgement

The text now says: “Thank you for shopping with CHWOO! Your order has been confirmed. Below is a summary of your purchase.” Actual saved items and totals are retained. No bank-processing or email-delivery claim is added.

Prepared/Accepted/Failed status logic, local-only mailbox restrictions and post-commit failure handling remain unchanged. No SMTP configuration or email sending occurred. The preview remains visible now. Once authorised local inbox delivery is configured and independently verified, the developer may set `delivery_verified=true` in app mail configuration. With delivery enabled and the order Accepted, the technical preview is hidden. The flag itself does not establish delivery; prepared/failed orders retain truthful diagnostics.

## F. Database and manual setup

The canonical schema now has nine tables. Only `customer_addresses` is new: unsigned auto-increment `id`, customer FK with delete cascade, recipient name/email/phone, address TEXT, CHAR(6) postal code, optional notes, creation timestamp and CHAR(64) fingerprint. Unique `(customer_id,fingerprint)` prevents duplicate saves. Existing order/inventory definitions and general seed data remain unchanged.

The installed store does not yet have this table. No live reset/import/schema change was performed. Preferred setup is an intentional empty/disposable fresh installation using the latest schema then unchanged seed. To preserve the installed store: back it up, select chwoo_db in phpMyAdmin, verify the unsigned InnoDB customer primary key and absence of customer_addresses, then execute only the complete customer_addresses CREATE TABLE statement from schema.sql in the SQL tab. Verify its Structure, unique key, FK and defaults. Do not import the entire schema/seed over populated tables. Exact steps are in database/README.md. No migration or local update SQL script was created.

## G. Security and preserved processing

Existing order transaction code is unchanged: customer/cart/detail revalidation, database-derived prices, integer-cent totals, row locking, guarded stock deductions, rollback, unique checkout-token idempotency and post-commit purchased-entry cleanup remain intact. New address operations use prepared statements, session ownership, authoritative PHP validation, escaped output and CSRF-protected POSTs. Authentication, wishlist, bag and notification behavior remain functional. No payment credentials are collected or stored.

## H. Tests performed

- Updated Task 06 runner: **58 checks passed**, including fresh schema/seed import in a disposable database, successful/failed provider outcomes, rollback after a second-item failure, concurrency/last-unit protection, duplicate submission, snapshots, ownership, address save/reuse/deduplication/one-off edits/removal/invalid-CSRF/foreign-account denial, and absence of any card table.
- Task 05: **55 checks passed inside the isolated suite**, and **55 passed separately against http://localhost:8000/chwoo**.
- Checkout validation JavaScript: **12 passed**; notification JavaScript: **9 passed**.
- Syntax: **32 PHP files and 10 JavaScript files passed**.
- Nine live HTTP smoke checks passed for home, Shop, New In pagination, product, bag, checkout and expected login redirects for payment, Addresses and confirmation.
- `git diff --check` passed. The staging area is empty.
- Disposable databases, records and servers were cleaned up. Existing store records/prices/stock were preserved; no legitimate accounts/orders were removed.

No visual browser verification was performed. HTTP/DOM and unit tests do not establish rendered spacing, focus behavior or animation appearance.

## I. Remaining setup/limits

Install the single saved-address table manually to enable persistence on the installed store. New-address checkout already works without it. Local acknowledgement delivery remains disabled/unverified; no SMTP setup is in scope. Rendered browser QA remains outstanding. Payment intentionally remains an assumed provider response with no real gateway, redirect or card functionality.

## J. Git

Branch remains feature/chwoo-orders. No staging, commit, push, merge or branch change. Working-tree changes are the files in section A; all modifications are unstaged and the new helper/test/report files are untracked. `git diff --check` passed. No unresolved merge is in progress.
