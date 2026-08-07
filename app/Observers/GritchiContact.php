<?php

namespace App\Observers;

use App\Abstracts\Observer;
use App\Models\Common\Contact;
use App\Models\Document\Document;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class GritchiContact extends Observer
{
    public function saved(Contact $contact): void
    {
        if ($this->shouldSyncProfile($contact)) {
            $this->postProfile($contact);
        }

        if ($this->shouldSyncFinance($contact)) {
            $this->postFinance($contact);
        }
    }

    private function postProfile(Contact $contact): void
    {
        try {
            $response = (new Client(['timeout' => 5]))->post($this->profileWebhookUrl(), [
                'headers' => $this->headers(),
                'json' => [
                    'event' => 'customer.saved',
                    'customer' => $this->contactPayload($contact),
                ],
            ]);

            Log::info('Gritchi contact webhook posted', [
                'status' => $response->getStatusCode(),
                'contact_id' => $contact->id,
                'email' => $contact->email,
            ]);
        } catch (GuzzleException $e) {
            Log::warning('Gritchi contact webhook failed: ' . $e->getMessage(), [
                'contact_id' => $contact->id,
                'email' => $contact->email,
            ]);
            report($e);
        }
    }

    private function postFinance(Contact $contact): void
    {
        try {
            $response = (new Client(['timeout' => 5]))->post($this->financeWebhookUrl(), [
                'headers' => $this->headers(),
                'json' => [
                    'event' => $contact->type . '.saved',
                    'contact' => $this->contactPayload($contact) + [
                        'record_type' => 'contact',
                        'contact_type' => $contact->type,
                        'xero_contact_id' => $this->xeroContactId($contact),
                        'linked_documents' => $this->linkedDocumentsPayload($contact),
                    ],
                ],
            ]);

            Log::info('Gritchi finance contact webhook posted', [
                'status' => $response->getStatusCode(),
                'contact_id' => $contact->id,
                'contact_type' => $contact->type,
                'email' => $contact->email,
                'xero_contact_id' => $this->xeroContactId($contact),
            ]);
        } catch (GuzzleException $e) {
            Log::warning('Gritchi finance contact webhook failed: ' . $e->getMessage(), [
                'contact_id' => $contact->id,
                'contact_type' => $contact->type,
                'email' => $contact->email,
                'xero_contact_id' => $this->xeroContactId($contact),
            ]);
            report($e);
        }
    }

    private function shouldSyncProfile(Contact $contact): bool
    {
        return (bool) $this->profileWebhookUrl()
            && $contact->type === Contact::CUSTOMER_TYPE
            && ! empty($contact->email)
            && ! $this->isPortalOriginRequest();
    }

    private function shouldSyncFinance(Contact $contact): bool
    {
        if (! $this->financeWebhookUrl()) {
            $this->logFinanceSkip($contact, 'missing_webhook_url');
            return false;
        }

        if (! in_array($contact->type, [Contact::CUSTOMER_TYPE, Contact::VENDOR_TYPE], true)) {
            $this->logFinanceSkip($contact, 'unsupported_type');
            return false;
        }

        if ($this->isPortalOriginRequest()) {
            $this->logFinanceSkip($contact, 'portal_origin');
            return false;
        }

        if (! $this->xeroContactId($contact) && empty($this->linkedDocumentsPayload($contact))) {
            $this->logFinanceSkip($contact, 'missing_xero_contact_or_linked_document');
            return false;
        }

        return true;
    }

    private function contactPayload(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'type' => $contact->type,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'address' => $contact->address,
            'city' => $contact->city,
            'state' => $contact->state,
            'zip_code' => $contact->zip_code,
            'country' => $contact->country,
            'reference' => $contact->reference,
            'updated_at' => optional($contact->updated_at)->toIso8601String(),
        ];
    }

    private function linkedDocumentsPayload(Contact $contact): array
    {
        return $contact->documents()
            ->whereIn('type', [Document::INVOICE_TYPE, Document::BILL_TYPE])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (Document $document) {
                return [
                    'id' => $document->id,
                    'type' => $document->type,
                    'number' => $document->document_number,
                    'xero_invoice_id' => $this->xeroInvoiceId($document),
                ];
            })
            ->filter(function (array $document) {
                return ! empty($document['xero_invoice_id']);
            })
            ->values()
            ->all();
    }

    private function xeroContactId(Contact $contact): ?string
    {
        if (preg_match('/xero[_\s-]*contact[_\s-]*id[:\s]+([a-z0-9-]+)/i', (string) $contact->reference, $match)) {
            return $match[1];
        }

        return null;
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

    private function logFinanceSkip(Contact $contact, string $reason): void
    {
        Log::info('Gritchi finance contact webhook skipped', [
            'reason' => $reason,
            'contact_id' => $contact->id,
            'contact_type' => $contact->type,
            'email' => $contact->email,
            'xero_contact_id' => $this->xeroContactId($contact),
        ]);
    }

    private function profileWebhookUrl(): ?string
    {
        return env('GRITCHI_PORTAL_WEBHOOK_URL');
    }

    private function financeWebhookUrl(): ?string
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

    private function isPortalOriginRequest(): bool
    {
        return request()->headers->get('X-Gritchi-Sync-Origin') === 'portal';
    }
}
