<?php namespace App\Ninja\Import\Ronin;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class RelationTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ($this->hasRelation($data->company)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'company'),
                'work_phone' => $this->getString($data, 'phone'),
                'contacts' => [
                    [
                        'first_name' => $this->getFirstName($data->name),
                        'last_name' => $this->getLastName($data->name),
                        'email' => $this->getString($data, 'email'),
                    ],
                ],
            ];
        });
    }
}
