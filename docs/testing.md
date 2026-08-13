# Testing

Run tests through Artisan:

```bash
php artisan test
```

Run PHPUnit directly:

```bash
vendor/bin/phpunit
```

Focused examples:

```bash
php artisan test tests/Feature/Sales/InvoicesTest.php
php artisan test tests/Feature/Purchases/BillsTest.php
php artisan test tests/Feature/Banking/TransactionsTest.php
php artisan test tests/Feature/Auth/UsersTest.php
```

Frontend build check:

```bash
npm run dev
```

Production asset build:

```bash
npm run prod
```

Before pushing a final feature commit:

```bash
npm run dev
php artisan test
```
