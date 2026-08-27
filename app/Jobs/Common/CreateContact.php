<?php

namespace App\Jobs\Common;

use App\Abstracts\Job;
use App\Events\Common\ContactCreated;
use App\Events\Common\ContactCreating;
use App\Events\Common\ContactUpdated;
use App\Interfaces\Job\HasOwner;
use App\Interfaces\Job\HasSource;
use App\Interfaces\Job\ShouldCreate;
use App\Jobs\Auth\CreateUser;
use App\Jobs\Common\CreateContactPersons;
use App\Models\Common\Contact;
use Illuminate\Support\Str;

class CreateContact extends Job implements HasOwner, HasSource, ShouldCreate
{
    protected bool $deduplicated = false;

    public function handle(): Contact
    {
        event(new ContactCreating($this->request));

        \DB::transaction(function () {
            if ($this->request->get('create_user', 'false') === 'true') {
                $this->createUser();
            }

            if ($contact = $this->existingSyncedContact()) {
                $this->model = $contact;
                $this->model->update($this->syncedContactAttributes());
                $this->deduplicated = true;

                return;
            }

            $this->model = Contact::create($this->request->all());

            // Upload logo
            if ($this->request->file('logo')) {
                $media = $this->getMedia($this->request->file('logo'), Str::plural($this->model->type));

                $this->model->attachMedia($media, 'logo');
            }

            $this->dispatch(new CreateContactPersons($this->model, $this->request));
        });

        if ($this->deduplicated) {
            event(new ContactUpdated($this->model, $this->request));
        } else {
            event(new ContactCreated($this->model, $this->request));
        }

        return $this->model;
    }

    protected function existingSyncedContact(): ?Contact
    {
        if (! $this->shouldDeduplicateSyncedContact()) {
            return null;
        }

        $company_id = $this->request->get('company_id');
        $type = $this->request->get('type');

        if (empty($company_id) || empty($type)) {
            return null;
        }

        $base = Contact::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $company_id)
            ->where('type', $type);

        if ($xero_contact_id = $this->xeroContactId()) {
            $contact = (clone $base)
                ->where('reference', 'like', '%' . $xero_contact_id . '%')
                ->orderBy('id')
                ->first();

            if ($contact) {
                return $contact;
            }
        }

        if ($email = trim((string) $this->request->get('email'))) {
            $contact = (clone $base)
                ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
                ->orderBy('id')
                ->first();

            if ($contact) {
                return $contact;
            }
        }

        if ($name = $this->normalizedName($this->request->get('name'))) {
            return (clone $base)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
                ->orderBy('id')
                ->first();
        }

        return null;
    }

    protected function shouldDeduplicateSyncedContact(): bool
    {
        return $this->request->headers->get('X-Gritchi-Sync-Origin') === 'portal'
            || ! empty($this->xeroContactId());
    }

    protected function xeroContactId(): ?string
    {
        if (preg_match('/xero[_\s-]*contact[_\s-]*id[:\s]+([a-z0-9-]+)/i', (string) $this->request->get('reference'), $match)) {
            return $match[1];
        }

        return null;
    }

    protected function syncedContactAttributes(): array
    {
        return collect($this->request->all())
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();
    }

    protected function normalizedName($name): ?string
    {
        $normalized = Str::lower(trim(preg_replace('/\s+/u', ' ', (string) $name)));

        return $normalized === '' ? null : $normalized;
    }

    public function createUser(): void
    {
        // Check if user exist
        if ($user = user_model_class()::where('email', $this->request['email'])->first()) {
            $message = trans('messages.error.customer', ['name' => $user->name]);

            throw new \Exception($message);
        }

        $customer_role_id = role_model_class()::all()->filter(function ($role) {
            return $role->hasPermission('read-client-portal');
        })->pluck('id')->first();

        $this->request->merge([
            'locale' => setting('default.locale', 'en-GB'),
            'roles' => $customer_role_id,
            'companies' => [$this->request->get('company_id')],
        ]);

        $user = $this->dispatch(new CreateUser($this->request));

        $this->request['user_id'] = $user->id;
    }
}
