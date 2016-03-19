<?php namespace App\Ninja\Import\Invoiceable;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class PaymentTransformer extends BaseTransformer
{
    public function transform($data, $maps)
    {
        return new Item($data, function ($data) use ($maps) {
            return [
                'amount' => $data->paid,
                'payment_date_sql' => $data->date_paid,
                'relation_id' => $data->relation_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}