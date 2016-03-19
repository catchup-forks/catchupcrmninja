<?php namespace App\Ninja\Import\Nutcache;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class RelationTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ($this->hasRelation($data->name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'name'),
                'city' => $this->getString($data, 'city'),
                'state' => $this->getString($data, 'stateprovince'),
                'id_number' => $this->getString($data, 'registration_number'),
                'postal_code' => $this->getString($data, 'postalzip_code'),
                'private_notes' => $this->getString($data, 'notes'),
                'work_phone' => $this->getString($data, 'phone'),
                'contacts' => [
                    [
                        'first_name' => isset($data->contact_name) ? $this->getFirstName($data->contact_name) : '',
                        'last_name' => isset($data->contact_name) ? $this->getLastName($data->contact_name) : '',
                        'email' => $this->getString($data, 'email'),
                        'phone' => $this->getString($data, 'mobile'),
                    ],
                ],
                'country_id' => isset($data->country) ? $this->getCountryId($data->country) : null,
            ];
        });
    }
}