# CHWOO

CHWOO is a server-rendered PHP/MySQLi demonstration store. The merged application
includes the catalogue, product variants, shopping bag, Outfit Builder (the
**LOOKBOOK** navigation item), customer accounts, wishlists, saved addresses,
checkout, order history and optional local acknowledgement email.

**Payment is simulated.** `Paid` / `Confirmed` are local demonstration statuses;
there is no payment gateway, external financial settlement or card-data storage.

## Windows XAMPP installation

1. Install Windows XAMPP with Apache, PHP and MariaDB (labelled **MySQL** in the
   XAMPP Control Panel). The development baseline is PHP 8.2.12 and MariaDB
   10.4.32. PHP needs MySQLi/mysqlnd, sessions and mbstring. The supplied integration
   tests also use cURL and DOM. Node is optional for JavaScript tests; it is not
   needed to serve the website. No npm build or Composer installation is required.
2. Place this checkout at `C:\xampp\htdocs\chwoo`. Configure Apache's
   `C:\xampp\apache\conf\httpd.conf` with `Listen 8000` and
   `ServerName localhost:8000`, keeping `DocumentRoot "C:/xampp/htdocs"`.
   Restart Apache after configuration changes, and start MySQL/MariaDB.
3. Open [local phpMyAdmin](http://localhost:8000/phpmyadmin/). On a fresh machine
   where `chwoo_db` is absent or intentionally empty, import
   `database/schema.sql` first, then `database/seed_products.sql` into the same
   MariaDB instance. Both scripts select `chwoo_db`; selecting another database
   in phpMyAdmin alone does not redirect the import. Inspect both imports for errors.
   **If an installed store already contains tables or records, stop: do not delete,
   overwrite or reimport it.** Use a separate empty local MariaDB instance for a
   fresh-install rehearsal; inspect an existing store against the canonical schema.
4. Verify **nine InnoDB tables** and these fresh seed counts:

   | Table | Rows |
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

   The seed assigns five demonstration units per variant, **320 units total**.
   These are fresh-install expectations, not counts for a store already in use.
5. Check `php/db.php` against the local MariaDB account. It connects to
   `localhost`, selects `chwoo_db` and sets `utf8mb4`. Configure any machine-specific
   credentials privately; never include passwords in shared source or submission
   evidence. Leave `CHWOO_DB_NAME` unset for the normal store; the integration
   runners use it only in their isolated processes. The schema uses MariaDB's
   `PERSISTENT` generated-column syntax; another database/version needs verification.
6. Open [CHWOO](http://localhost:8000/chwoo/). Check Home, the latest-eight New In listing,
   Shop filtering/sorting, a product's colour/size/price, LOOKBOOK's Top + Bottom
   and Dress modes, and bag updates. On a disposable demonstration installation,
   register a fictitious customer, exercise Wishlist and Saved Addresses, and
   complete delivery → review → simulated payment → confirmation → Account Orders.
   A completed simulation writes orders and deducts stock, so perform this
   walkthrough only with disposable data. Mail is optional and disabled by default.

See [database/README.md](database/README.md) for relationships, catalogue caveats
and existing-installation inspection. All 16 seeded product images are supplied.
Collections still expects the teammate's approved `midsommar-editorial-02.jpg`
and `midsommar-outfit.jpg` in `C:\xampp\htdocs\chwoo\images\collections`;
labelled placeholders remain until those files are supplied.

## Local order acknowledgement email

Saved, confirmed purchases produce a branded HTML acknowledgement with a plain-text
alternative. Both use the saved order/item/delivery snapshots. The **View My Orders**
link opens `php/account.php?section=orders` and requires the customer's normal login.
No product images or external assets are loaded by the email.

Tracked `php/mail_config.php` stays disabled with empty mailboxes. The application
merges an optional **gitignored** `php/mail_config.local.php` over those defaults.
Never enable delivery in the tracked file or use a customer's checkout email as
the configured recipient.

To configure another Windows XAMPP / Mercury installation, provision the authorised
local sender and recipient mailboxes, then:

1. Start Apache on port 8000 and Mercury SMTP/POP3 on ports 25/110.
2. In the PHP configuration used by Apache, keep `SMTP=localhost`, `smtp_port=25`
   and an empty `sendmail_path`. Restart Apache if PHP settings changed. Thunderbird
   receives the authorised `chwoo-inbox@localhost` mailbox through POP3 on port 110.
3. Create `php/mail_config.local.php` using your authorised local mailbox names
   (the following are examples, not credentials):

   ```php
   <?php
   return [
       'enabled' => true,
       'delivery_verified' => false,
       'recipient' => 'chwoo-inbox@localhost',
       'sender' => 'chwoo-store@localhost',
       'site_url' => 'http://localhost:8000/chwoo',
   ];
   ```

4. Check `git check-ignore -v php/mail_config.local.php` before sharing changes.
   Set `enabled` to `false` in this local file, or remove the file, to disable mail.

The ignored override is machine-local and is not supplied by a clean checkout.
Keep mailbox passwords in the local Mercury/Thunderbird account configuration,
outside the repository. Set `delivery_verified` to true only after your own manual
local verification; it neither proves delivery of a later message nor changes the
customer's confirmation page. In Thunderbird, use the provisioned local recipient
as the account username, POP3 server `localhost:110` and outgoing SMTP server
`localhost:25`; match authentication settings to your local Mercury setup.

`site_url` must be an absolute HTTP(S) base URL without credentials, query or
fragment; it defaults to `http://localhost:8000/chwoo`. It is never derived from
request headers. Sender and recipient must each be one explicitly authorised
`@localhost` mailbox; external addresses, recipient lists and header injection are
rejected. PHP SMTP must use loopback and `sendmail_path` must remain empty. No API,
Composer package or external mail service is used.

Acknowledgement runs only after a new purchase commits. Payment replay and
confirmation refresh do not send again, including when delivery was disabled or
failed. `Prepared`, `Accepted`, `Failed` and the acceptance timestamp remain internal
database diagnostics. `Accepted` means PHP's local mail transport accepted the
message, not that an external customer or local inbox received it. Mail failure
cannot roll back the confirmed purchase. There is no automatic resend facility.

**Historical manual evidence (not rerun for Task 08B):** verification was recorded
on **31 October 2026** in the configured local
environment. Mercury SMTP and POP3 worked locally, and PHP `mail()` successfully
sent through Mercury. A real CHWOO purchase, **Order #5**, generated the branded
HTML acknowledgement, which was received and opened in Thunderbird at
`chwoo-inbox@localhost`. The email contained the correct purchased item, pricing
and order details. Refreshing the confirmation page did not generate a duplicate
email. Automated regression tests also passed.

This verifies delivery in that configured local environment; it does not verify
delivery to external customer email addresses or another machine. Repeat the
local inbox check after configuring a different machine. Opening or replaying an
existing order will not send a new acknowledgement, and no installed order records
need to be edited for setup.

## Verification

Run from the repository root in PowerShell:

```powershell
C:\xampp\php\php.exe -d disable_functions=mail tests\order_mail.php
C:\xampp\php\php.exe -d disable_functions=mail tests\task06.php
C:\xampp\php\php.exe -d disable_functions=mail tests\task07c.php
node tests\product-selection.mjs
node tests\checkout-validation.mjs
node tests\checkout-history.mjs
node tests\notifications.mjs
```

The mail suite uses in-memory snapshots and mock transports, without database
access. Tasks 06 and 07C require local MariaDB; run them sequentially with no
concurrent store activity so the installed-record comparisons remain meaningful.
Each creates/removes only a randomly named
disposable database and isolated PHP server, and includes Task 05 and saved-address
regressions. The test server has `mail()` disabled and prepare-only mode enabled,
and explicit acceptance/failure tests inject mocks. Installed store records are
compared before/after and must remain unchanged. Task 07C also runs `task07c2.php`
and `task07d1.php`, covering outfit/bag integration, navigation, Wishlist → Orders
authentication and Collections image paths. These included files are not standalone
runners. Do not run Task 05 separately against the installed store. If temporary
storage is restricted, point process-local `TEMP` and `TMP` at a writable scratch
directory before launching PHP. JavaScript checks simulate events and do not
establish browser or Thunderbird rendering. Test definitions and historical notes
do not establish a pass until the corresponding command has actually completed.

Database setup and snapshot fields are documented in [database/README.md](database/README.md).
