<?php namespace App\Ninja\Import\Harvest;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class ContactTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ( ! $this->hasRelation($data->relation)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'relation_id' => $this->getRelationId($data->relation),
                'first_name' => $this->getString($data, 'first_name'),
                'last_name' => $this->getString($data, 'last_name'),
                'email' => $this->getString($data, 'email'),
                'phone' => $this->getString($data, 'office_phone') ?: $this->getString($data, 'mobile_phone'),
            ];
        });
    }
}
