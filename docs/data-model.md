# Data Model

Akaunting has a large accounting data model. These are the main areas used by the Gritchi integration.

## Core Records

`users`

- Akaunting login users.
- Portal SSO finds or creates users by email.
- Users are linked to companies and roles.

`companies`

- Accounting company context.
- Local setup creates the `Gritchi` company.
- Portal SSO assigns users to an existing enabled company.

`user_companies`

- Connects users to companies.
- Updated during Gritchi SSO when the user needs access.

`user_roles`

- Connects users to roles.
- Gritchi SSO uses `GRITCHI_SSO_ROLE`, default `admin`.

`contacts`

- Customers and vendors.
- Customer profile changes can be sent to Portal.
- Customer and vendor records can send finance webhooks when linked to Xero data.

`documents`

- Invoices and bills.
- Document saves and deletes can send finance webhooks to Portal.

`document_items`

- Line items for invoices and bills.
- Item saves can trigger document-level finance sync.

`transactions`

- Income and expense payments.
- Payment saves can send finance webhooks to Portal.

`accounts`

- Bank accounts.
- Bank account saves can send finance webhooks to Portal.

`categories`

- Income and expense categories.
- Category saves can send finance webhooks when linked to a Xero account.

## Xero ID Markers

Portal imports Xero data into Akaunting and stores Xero identifiers in text fields that the Gritchi observers can read.

Common markers:

- `xero_invoice_id: <id>` in document notes, order number, or document number.
- `xero_payment_id: <id>` or `xero-pay-<id>` in transaction number, reference, or description.
- `xero_account_id: <id>` in account bank address or category description.
- `xero:<id>` in `created_from` for imported accounts and categories.
- `xero_contact_id: <id>` in contact reference.
