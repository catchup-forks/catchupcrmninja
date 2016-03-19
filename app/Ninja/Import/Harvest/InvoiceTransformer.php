<?php namespace App\Ninja\Import\Harvest;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class InvoiceTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ( ! $this->getRelationId($data->relation)) {
            return false;
        }

        if ($this->hasInvoice($data->id)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'relation_id' => $this->getRelationId($data->relation),
                'invoice_number' => $this->getInvoiceNumber($data->id),
                'paid' => (float) $data->paid_amount,
                'po_number' => $this->getString($data, 'po_number'),
                'invoice_date_sql' => $this->getDate($data->issue_date, 'm/d/Y'),
                'invoice_items' => [
                    [
                        'product_key' => '',
                        'notes' => $this->getString($data, 'subject'),
                        'cost' => (float) $data->invoice_amount,
                        'qty' => 1,
                    ]
                ],
            ];
        });
    }
}