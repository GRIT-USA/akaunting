# Authentication And Syncing

This is the main Gritchi-specific behavior inside Akaunting. Portal owns access decisions, while Akaunting owns Finance records and posts selected changes back to Portal.

## Standard Akaunting Auth

Akaunting still supports its normal login flow:

```text
/auth/login
```

Local default admin:

- Email: `admin@akaunting.local`
- Password: `password12345`

## Portal SSO Auth

Portal launches Finance users into Akaunting through:

```text
/gritchi/sso?token=<signed-token>
```

The route is handled by:

```text
app/Http/Controllers/Gritchi/SsoController.php
```

## SSO Token Validation

Akaunting validates:

- Token has three JWT-style parts.
- Signature matches `GRITCHI_SSO_SECRET`.
- Issuer is `gritchi-portal`.
- Audience is `akaunting`.
- Token has not expired.
- Email is present.

Shared secret:

```env
GRITCHI_SSO_SECRET=
```

This must match Portal.

## SSO User Setup

After token validation:

1. Akaunting finds the user by email.
2. If missing, Akaunting creates the user.
3. If soft-deleted, Akaunting restores the user.
4. Akaunting enables the user.
5. Akaunting attaches the user to a company.
6. Akaunting assigns the configured role.
7. Akaunting seeds dashboards if needed.
8. Akaunting logs the user in.
9. User lands on the company dashboard.

Configurable role:

```env
GRITCHI_SSO_ROLE=admin
```

Configurable locale:

```env
GRITCHI_SSO_LOCALE=en-GB
```

## Portal-Origin Writes

Portal writes imported or synced finance data into Akaunting.

Those requests should include:

```text
X-Gritchi-Sync-Origin: portal
```

Akaunting observers skip outbound Gritchi webhooks when this header is present. This prevents immediate sync loops.

## Outbound Profile Sync

Contact profile sync is handled by:

```text
app/Observers/GritchiContact.php
```

Customer contact saves can post to Portal:

```env
GRITCHI_PORTAL_WEBHOOK_URL=
```

Payload event:

```text
customer.saved
```

The observer skips profile sync when:

- Profile webhook URL is missing.
- Contact is not a customer.
- Contact email is empty.
- Request came from Portal.

## Outbound Finance Sync

Finance sync is handled by:

```text
app/Observers/GritchiFinance.php
```

Finance webhooks post to:

```env
GRITCHI_PORTAL_FINANCE_WEBHOOK_URL=
```

Supported events:

- `customer.saved`
- `vendor.saved`
- `invoice.saved`
- `bill.saved`
- `invoice.deleted`
- `bill.deleted`
- `payment.saved`
- `account.saved`
- `category.saved`

## Webhook Secret

Akaunting sends the shared secret header:

```text
X-Gritchi-Webhook-Secret
```

Secret env:

```env
GRITCHI_WEBHOOK_SECRET=
WEBHOOK_SHARED_SECRET=
```

This must match Portal's `WEBHOOK_SHARED_SECRET`.

## Xero Link Markers

Portal imports Xero data into Akaunting and leaves markers so Akaunting can identify linked records later.

Common markers:

- `xero_invoice_id: <id>`
- `xero_payment_id: <id>`
- `xero-pay-<id>`
- `xero_account_id: <id>`
- `xero:<id>`
- `xero_contact_id: <id>`

These markers are read from fields such as notes, order number, document number, reference, description, bank address, category description, and `created_from`.

## Sync Debugging

If Portal does not receive a change:

1. Check Akaunting logs for `Gritchi ... webhook skipped`.
2. Check whether the request had `X-Gritchi-Sync-Origin: portal`.
3. Confirm the record type is supported.
4. Confirm the record has a contact email or Xero marker.
5. Confirm webhook URL and secret env values.
6. Check Portal `sync_events`.
