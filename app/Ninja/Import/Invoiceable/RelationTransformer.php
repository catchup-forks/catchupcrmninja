<?php namespace App\Ninja\Import\Invoiceable;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class RelationTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ($this->hasRelation($data->relation_name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'relation_name'),
                'work_phone' => $this->getString($data, 'tel'),
                'website' => $this->getString($data, 'website'),
                'address1' => $this->getString($data, 'address'),
                'city' => $this->getString($data, 'city'),
                'state' => $this->getString($data, 'state'),
                'postal_code' => $this->getString($data, 'postcode'),
                'country_id' => $this->getCountryIdBy2($data->country),
                'private_notes' => $this->getString($data, 'notes'),
                'contacts' => [
                    [
                        'email' => $this->getString($data, 'email'),
                        'phone' => $this->getString($data, 'mobile'),
                    ],
                ],
            ];
        });
    }
}
