<?php namespace App\Ninja\Import\Harvest;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class PaymentTransformer extends BaseTransformer
{
    public function transform($data)
    {
        return new Item($data, function ($data) {
            return [
                'amount' => $data->paid_amount,
                'payment_date_sql' => $this->getDate($data->last_payment_date, 'm/d/Y'),
                'relation_id' => $data->relation_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}