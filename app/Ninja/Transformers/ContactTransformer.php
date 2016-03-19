<?php namespace App\Ninja\Transformers;

use App\Models\Organisation;
use App\Models\Contact;
use League\Fractal;

class ContactTransformer extends EntityTransformer
{
    public function transform(Contact $contact)
    {
        return [
            'id' => (int) $contact->public_id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'updated_at' => $this->getTimestamp($contact->updated_at),
            'archived_at' => $this->getTimestamp($contact->deleted_at),
            'is_primary' => (bool) $contact->is_primary,
            'phone' => $contact->phone,
            'last_login' => $contact->last_login,
            'organisation_key' => $this->organisation->organisation_key,
            'send_invoice' => (bool) $contact->send_invoice,
        ];
    }
}