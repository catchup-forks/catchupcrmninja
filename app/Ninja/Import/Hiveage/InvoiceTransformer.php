<?php namespace App\Ninja\Import\Hiveage;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class InvoiceTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ( ! $this->getRelationId($data->relation)) {
            return false;
        }

        if ($this->hasInvoice($data->statement_no)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'relation_id' => $this->getRelationId($data->relation),
                'invoice_number' => $this->getInvoiceNumber($data->statement_no),
                'paid' => (float) $data->paid_total,
                'invoice_date_sql' => $this->getDate($data->date),
                'due_date_sql' => $this->getDate($data->due_date),
                'invoice_items' => [
                    [
                        'product_key' => '',
                        'notes' => $this->getString($data, 'summary'),
                        'cost' => (float) $data->billed_total,
                        'qty' => 1,
                    ]
                ],
            ];
        });
    }
}