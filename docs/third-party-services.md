# Third-Party Services

## Gritchi Portal

Portal is the source of Finance role launches and receives Akaunting profile and finance webhook payloads.

## Xero

Xero data is imported by Portal into Akaunting. Akaunting keeps Xero identifiers in imported records so changes can be linked back to Xero through Portal.

## MySQL or MariaDB

Primary Akaunting database.

## Mail Provider

Akaunting supports mail delivery through Laravel mail configuration. In Gritchi local development, Akaunting user invitations can be skipped with `GRITCHI_SKIP_USER_INVITATIONS=true`.

## Sentry and Bugsnag

Packages are present for error tracking. Configure them only when the deployed environment uses them.

## Akaunting Modules

The repository includes module support and bundled payment modules such as Offline Payments and PayPal Standard.
