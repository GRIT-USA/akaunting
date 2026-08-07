<?php

namespace App\Observers;

use App\Abstracts\Observer;
use App\Models\Banking\Account;
use App\Models\Banking\Transaction;
use App\Models\Document\Document;
use App\Models\Document\DocumentItem;
use App\Models\Setting\Category;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class GritchiFinance extends Observer
{
    public function syncDocument(Document $document): void
    {
        $this->syncFinanceDocument($document);
    }

    public function saved($model): void
    {
        if ($model instanceof Account) {
            $this->syncAccount($model);
            return;
        }

        if ($model instanceof Category) {
            $this->syncCategory($model);
            return;
        }

        if ($model instanceof Document) {
            $this->syncFinanceDocument($model);
            return;
        }

        if ($model instanceof DocumentItem) {
            $this->syncDocumentItem($model);
            return;
        }

        if ($model instanceof Transaction) {
            $this->syncPayment($model);
        }
    }

    private function syncAccount(Account $account): void
    {
        if (! $this->shouldSyncAccount($account)) {
            return;
        }

        $this->post([
            'event' => 'account.saved',
            'account' => [
                'record_type' => 'account',
                'id' => $account->id,
                'type' => $account->type,
                'name' => $account->name,
                'number' => $account->number,
                'currency_code' => $account->currency_code,
                'opening_balance' => $account->opening_balance,
                'bank_name' => $account->bank_name,
                'bank_phone' => $account->bank_phone,
                'bank_address' => $account->bank_address,
                'enabled' => (bool) $account->enabled,
                'created_from' => $account->created_from,
                'xero_account_id' => $this->xeroAccountId($account),
            ],
        ]);
    }

    private function syncCategory(Category $category): void
    {
        if (! $this->shouldSyncCategory($category)) {
            return;
        }

        $this->post([
            'event' => 'category.saved',
            'category' => [
                'record_type' => 'category',
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'type' => $category->type,
                'color' => $category->color,
                'description' => $category->description,
                'enabled' => (bool) $category->enabled,
                'created_from' => $category->created_from,
                'xero_account_id' => $this->xeroCategoryAccountId($category),
            ],
        ]);
    }

    public function deleted($model): void
    {
        if ($model instanceof Document) {
            $this->syncFinanceDocument($model, 'deleted', $model->type . '.deleted');
            return;
        }

        if ($model instanceof DocumentItem) {
            $this->syncDocumentItem($model);
        }
    }

    private function syncDocumentItem(DocumentItem $item): void
    {
        $document = $item->document;

        if ($document instanceof Document && in_array($document->type, [Document::INVOICE_TYPE, Document::BILL_TYPE], true)) {
            $this->syncFinanceDocument($document);
        }
    }

    private function syncFinanceDocument(Document $document, ?string $statusOverride = null, ?string $eventOverride = null): void
    {
        $document->unsetRelation('contact');
        $document->unsetRelation('items');
        $document->load(['contact', 'items']);

        if (! $this->shouldSyncDocument($document)) {
            return;
        }

        $this->post([
            'event' => $eventOverride ?: $document->type . '.saved',
            'invoice' => [
                'record_type' => $document->type,
                'type' => $document->type,
                'id' => $document->id,
                'number' => $document->document_number,
                'document_number' => $document->document_number,
                'invoice_number' => $document->document_number,
                'order_number' => $document->order_number,
                'reference' => $document->order_number,
                'notes' => $document->notes,
                'xero_invoice_id' => $this->xeroInvoiceId($document),
                'status' => $statusOverride ?: $document->status,
                'amount' => $document->amount,
                'total' => $document->amount,
                'paid_amount' => $document->paid,
                'balance' => max((float) $document->amount - (float) $document->paid, 0),
                'currency' => $document->currency_code,
                'currency_code' => $document->currency_code,
                'issued_on' => optional($document->issued_at)->toDateString(),
                'due_on' => optional($document->due_at)->toDateString(),
                'customer' => $this->customerPayload($document),
                'contact' => $this->customerPayload($document),
                'items' => $this->invoiceItemsPayload($document),
            ],
        ]);
    }

    private function syncPayment(Transaction $transaction): void
    {
        if (! $this->shouldSyncPayment($transaction)) {
            return;
        }

        $this->post([
            'event' => 'payment.saved',
            'payment' => [
                'id' => $transaction->id,
                'number' => $transaction->number,
                'reference' => $transaction->reference,
                'description' => $transaction->description,
                'xero_payment_id' => $this->xeroPaymentId($transaction),
                'status' => 'paid',
                'amount' => $transaction->amount,
                'currency' => $transaction->currency_code,
                'paid_on' => optional($transaction->paid_at)->toDateString(),
                'invoice_id' => $transaction->document_id,
                'account_id' => $transaction->account_id,
                'reconciled' => (bool) $transaction->reconciled,
                'type' => $transaction->type,
                'record_type' => 'payment',
                'customer' => $this->customerPayload($transaction),
                'contact' => $this->customerPayload($transaction),
            ],
        ]);
    }

    private function shouldSyncDocument(Document $document): bool
    {
        if (! $this->webhookUrl()) {
            $this->logInvoiceSkip($document, 'missing_webhook_url');
            return false;
        }

        if (! in_array($document->type, [Document::INVOICE_TYPE, Document::BILL_TYPE], true)) {
            $this->logInvoiceSkip($document, 'unsupported_type');
            return false;
        }

        if ($this->isPortalOriginRequest()) {
            $this->logInvoiceSkip($document, 'portal_origin');
            return false;
        }

        if (! $this->xeroInvoiceId($document) && ! $this->contactEmail($document)) {
            $this->logInvoiceSkip($document, 'missing_xero_invoice_id_and_contact_email');
            return false;
        }

        return true;
    }

    private function shouldSyncPayment(Transaction $transaction): bool
    {
        if (! $this->webhookUrl()) {
            $this->logPaymentSkip($transaction, 'missing_webhook_url');
            return false;
        }

        if (! in_array($transaction->type, [Transaction::INCOME_TYPE, Transaction::EXPENSE_TYPE], true)) {
            $this->logPaymentSkip($transaction, 'unsupported_type');
            return false;
        }

        if ($this->isPortalOriginRequest()) {
            $this->logPaymentSkip($transaction, 'portal_origin');
            return false;
        }

        if (! $this->xeroPaymentId($transaction) && ! $this->contactEmail($transaction)) {
            $this->logPaymentSkip($transaction, 'missing_xero_payment_id_and_contact_email');
            return false;
        }

        return true;
    }

    private function shouldSyncAccount(Account $account): bool
    {
        if (! $this->webhookUrl()) {
            $this->logAccountSkip($account, 'missing_webhook_url');
            return false;
        }

        if ($account->type !== 'bank') {
            $this->logAccountSkip($account, 'unsupported_type');
            return false;
        }

        if ($this->isPortalOriginRequest()) {
            $this->logAccountSkip($account, 'portal_origin');
            return false;
        }

        return true;
    }

    private function shouldSyncCategory(Category $category): bool
    {
        if (! $this->webhookUrl()) {
            $this->logCategorySkip($category, 'missing_webhook_url');
            return false;
        }

        if ($this->isPortalOriginRequest()) {
            $this->logCategorySkip($category, 'portal_origin');
            return false;
        }

        if (! $this->xeroCategoryAccountId($category)) {
            $this->logCategorySkip($category, 'missing_xero_account_id');
            return false;
        }

        return true;
    }

    private function post(array $payload): void
    {
        try {
            $response = (new Client(['timeout' => 5]))->post($this->webhookUrl(), [
                'headers' => $this->headers(),
                'json' => $payload,
            ]);

            Log::info('Gritchi finance webhook posted', [
                'event' => $payload['event'] ?? null,
                'status' => $response->getStatusCode(),
                'document_type' => $payload['invoice']['record_type'] ?? null,
                'invoice_number' => $payload['invoice']['number'] ?? null,
                'xero_invoice_id' => $payload['invoice']['xero_invoice_id'] ?? null,
                'payment_number' => $payload['payment']['number'] ?? null,
                'xero_payment_id' => $payload['payment']['xero_payment_id'] ?? null,
                'account_id' => $payload['account']['id'] ?? null,
                'account_number' => $payload['account']['number'] ?? null,
                'xero_account_id' => $payload['account']['xero_account_id'] ?? null,
                'category_id' => $payload['category']['id'] ?? null,
                'category_name' => $payload['category']['name'] ?? null,
                'category_code' => $payload['category']['code'] ?? null,
                'xero_category_account_id' => $payload['category']['xero_account_id'] ?? null,
            ]);
        } catch (GuzzleException $e) {
            Log::warning('Gritchi finance webhook failed: ' . $e->getMessage(), [
                'event' => $payload['event'] ?? null,
                'document_type' => $payload['invoice']['record_type'] ?? null,
                'invoice_number' => $payload['invoice']['number'] ?? null,
                'xero_invoice_id' => $payload['invoice']['xero_invoice_id'] ?? null,
                'payment_number' => $payload['payment']['number'] ?? null,
                'xero_payment_id' => $payload['payment']['xero_payment_id'] ?? null,
                'account_id' => $payload['account']['id'] ?? null,
                'account_number' => $payload['account']['number'] ?? null,
                'xero_account_id' => $payload['account']['xero_account_id'] ?? null,
                'category_id' => $payload['category']['id'] ?? null,
                'category_name' => $payload['category']['name'] ?? null,
                'category_code' => $payload['category']['code'] ?? null,
                'xero_category_account_id' => $payload['category']['xero_account_id'] ?? null,
            ]);
            report($e);
        }
    }

    private function customerPayload($model): array
    {
        $contact = $model->contact;

        return [
            'id' => optional($contact)->id,
            'name' => optional($contact)->name ?: ($model->contact_name ?? null),
            'email' => $this->contactEmail($model),
            'phone' => optional($contact)->phone ?: ($model->contact_phone ?? null),
        ];
    }

    private function contactEmail($model): ?string
    {
        return optional($model->contact)->email ?: ($model->contact_email ?? null);
    }

    private function logInvoiceSkip(Document $document, string $reason): void
    {
        Log::info('Gritchi finance webhook skipped', [
            'reason' => $reason,
            'document_type' => $document->type,
            'document_id' => $document->id,
            'invoice_number' => $document->document_number,
            'contact_email' => $this->contactEmail($document),
            'xero_invoice_id' => $this->xeroInvoiceId($document),
        ]);
    }

    private function logPaymentSkip(Transaction $transaction, string $reason): void
    {
        Log::info('Gritchi payment webhook skipped', [
            'reason' => $reason,
            'transaction_id' => $transaction->id,
            'transaction_number' => $transaction->number,
            'contact_email' => $this->contactEmail($transaction),
            'xero_payment_id' => $this->xeroPaymentId($transaction),
        ]);
    }

    private function logAccountSkip(Account $account, string $reason): void
    {
        Log::info('Gritchi account webhook skipped', [
            'reason' => $reason,
            'account_id' => $account->id,
            'account_name' => $account->name,
            'account_number' => $account->number,
            'xero_account_id' => $this->xeroAccountId($account),
        ]);
    }

    private function logCategorySkip(Category $category, string $reason): void
    {
        Log::info('Gritchi category webhook skipped', [
            'reason' => $reason,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'category_code' => $category->code,
            'category_type' => $category->type,
            'xero_account_id' => $this->xeroCategoryAccountId($category),
        ]);
    }

    private function invoiceItemsPayload(Document $document): array
    {
        return $document->items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description ?: $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'unit_price' => $item->price,
                'amount' => $item->total,
                'total' => $item->total,
            ];
        })->values()->all();
    }

    private function xeroInvoiceId(Document $document): ?string
    {
        $text = implode("\n", array_filter([
            $document->notes,
            $document->order_number,
            $document->document_number,
        ]));

        if (preg_match('/xero[_\s-]*invoice[_\s-]*id[:\s]+([a-z0-9-]+)/i', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    private function xeroPaymentId(Transaction $transaction): ?string
    {
        $text = implode("\n", array_filter([
            $transaction->number,
            $transaction->reference,
            $transaction->description,
        ]));

        if (preg_match('/xero-pay-([a-z0-9-]+)/i', $text, $match)) {
            return $match[1];
        }

        if (preg_match('/xero[_\s-]*payment[_\s-]*id[:\s]+([a-z0-9-]+)/i', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    private function xeroAccountId(Account $account): ?string
    {
        if (preg_match('/xero[:\s-]+([a-z0-9-]+)/i', (string) $account->created_from, $match)) {
            return $match[1];
        }

        if (preg_match('/xero[_\s-]*account[_\s-]*id[:\s]+([a-z0-9-]+)/i', (string) $account->bank_address, $match)) {
            return $match[1];
        }

        return null;
    }

    private function xeroCategoryAccountId(Category $category): ?string
    {
        if (preg_match('/xero[:\s-]+([a-z0-9-]+)/i', (string) $category->created_from, $match)) {
            return $match[1];
        }

        if (preg_match('/xero[_\s-]*account[_\s-]*id[:\s]+([a-z0-9-]+)/i', (string) $category->description, $match)) {
            return $match[1];
        }

        return null;
    }

    private function isPortalOriginRequest(): bool
    {
        return request()->headers->get('X-Gritchi-Sync-Origin') === 'portal';
    }

    private function webhookUrl(): ?string
    {
        if ($url = env('GRITCHI_PORTAL_FINANCE_WEBHOOK_URL')) {
            return rtrim($url, '/');
        }

        if (! $url = env('GRITCHI_PORTAL_WEBHOOK_URL')) {
            return null;
        }

        $url = rtrim($url, '/');

        if (str_ends_with($url, '/webhooks/akaunting/finance')) {
            return $url;
        }

        if (str_ends_with($url, '/webhooks/akaunting')) {
            return $url . '/finance';
        }

        return $url . '/webhooks/akaunting/finance';
    }

    private function headers(): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($secret = env('GRITCHI_WEBHOOK_SECRET') ?: env('WEBHOOK_SHARED_SECRET')) {
            $headers['X-Gritchi-Webhook-Secret'] = $secret;
        }

        return $headers;
    }
}
