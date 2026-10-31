# CHWOO

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
3. Create `php/mail_config.local.php` with exactly:

   ```php
   <?php
   return [
       'enabled' => true,
       'delivery_verified' => true,
       'recipient' => 'chwoo-inbox@localhost',
       'sender' => 'chwoo-store@localhost',
       'site_url' => 'http://localhost:8000/chwoo',
   ];
   ```

4. Check `git check-ignore -v php/mail_config.local.php` before sharing changes.
   Set `enabled` to `false` in this local file, or remove the file, to disable mail.

The existing verified settings have been moved into that ignored file in this
working copy. It contains no password. `delivery_verified` records the developer's
manual local verification only; it neither proves delivery of a later message nor
changes the customer's confirmation page.

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

Manual verification was completed on **31 October 2026** in the configured local
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
node tests\checkout-validation.mjs
node tests\checkout-history.mjs
node tests\notifications.mjs
```

The mail suite uses in-memory snapshots and mock transports, without database
access. Task 06 requires local MariaDB; it creates/removes only a randomly named
disposable database and isolated PHP server, and includes Task 05 and saved-address
regressions. The test server has `mail()` disabled and prepare-only mode enabled,
and explicit acceptance/failure tests inject mocks. Installed store records are
compared before/after and must remain unchanged. Do not run Task 05 separately
against the installed store for this task. JavaScript checks simulate events and
do not establish browser or Thunderbird rendering.

Database setup and snapshot fields are documented in [database/README.md](database/README.md).
