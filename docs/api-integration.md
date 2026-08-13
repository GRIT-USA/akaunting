# API Integration

## Gritchi Portal SSO

Portal launches Finance users into:

```text
GET /gritchi/sso?token=<signed-token>
```

Akaunting validates:

- Token structure.
- HMAC SHA-256 signature.
- `iss` equals `gritchi-portal`.
- `aud` equals `akaunting`.
- `exp` has not passed.
- User email is present.

After validation, Akaunting:

- Finds or creates the user by email.
- Restores soft-deleted users.
- Enables the user.
- Assigns company access.
- Assigns the configured role.
- Logs the user in.

## Portal Webhooks

Akaunting posts profile webhooks to:

```text
GRITCHI_PORTAL_WEBHOOK_URL
```

Akaunting posts finance webhooks to:

```text
GRITCHI_PORTAL_FINANCE_WEBHOOK_URL
```

If only `GRITCHI_PORTAL_WEBHOOK_URL` is set, the finance observer derives the finance URL from it.

Webhook requests include:

```text
X-Gritchi-Webhook-Secret: <secret>
```

## Portal-Origin Loop Guard

Akaunting skips outbound Gritchi webhooks when the request contains:

```text
X-Gritchi-Sync-Origin: portal
```

Portal uses this header for writes that originated from Portal or Xero imports so Akaunting does not immediately echo those changes back.

## Finance Events

Supported outbound events include:

- `customer.saved`
- `vendor.saved`
- `invoice.saved`
- `bill.saved`
- `invoice.deleted`
- `bill.deleted`
- `payment.saved`
- `account.saved`
- `category.saved`

## API Routes

The standard Akaunting API exposes resources for:

- Users.
- Companies.
- Items.
- Contacts.
- Documents.
- Document transactions.
- Accounts.
- Reconciliations.
- Transactions.
- Transfers.
- Reports.
- Categories.
- Currencies.
- Taxes.
- Settings.
- Translations.
