# Architecture

Akaunting is the Finance workspace in the Gritchi platform.

The app owns:

- Customers and vendors.
- Invoices and bills.
- Payments and expenses.
- Bank accounts.
- Categories.
- Transfers and transactions.
- Finance user access inside Akaunting.

The app does not own:

- Portal invitations.
- Portal role assignment.
- GHL contact import.
- Xero OAuth connection state.
- Portal sync audit history.

## Main Areas

- `app/Http/Controllers`: Laravel controllers for auth, admin, portal, API, settings, banking, sales, purchases, and Gritchi SSO.
- `app/Models`: Akaunting domain models for users, companies, contacts, documents, transactions, accounts, categories, and settings.
- `app/Jobs`: Business operations for creating and updating records.
- `app/Observers`: Model observers, including Gritchi webhook observers.
- `routes`: Web, API, admin, portal, guest, install, signed, preview, and console routes.
- `resources`: Blade views, Vue assets, language files, and frontend code.
- `modules`: Akaunting modules such as Offline Payments and PayPal Standard.

## Gritchi-Specific Architecture

`app/Http/Controllers/Gritchi/SsoController.php`

- Consumes Portal SSO tokens from `/gritchi/sso`.
- Verifies token signature, issuer, audience, and expiry.
- Finds or creates the Akaunting user.
- Restores and enables the user when needed.
- Ensures company access and role assignment.
- Signs the user into Akaunting.

`app/Observers/GritchiContact.php`

- Sends customer profile webhooks to Portal.
- Sends contact finance webhooks for customers and vendors linked to Xero data.

`app/Observers/GritchiFinance.php`

- Sends finance webhooks for invoices, bills, payments, bank accounts, and categories.
- Skips Portal-origin requests to avoid sync loops.
- Extracts Xero IDs from Akaunting fields used by Portal imports.

`app/Providers/Observer.php`

- Registers the Gritchi contact and finance observers.
