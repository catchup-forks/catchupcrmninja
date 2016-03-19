<?php namespace App\Ninja\Transformers;

use App\Models\Organisation;
use App\Models\Credit;
use League\Fractal;

class CreditTransformer extends EntityTransformer
{
    public function transform(Credit $credit)
    {
        return [
            'id' => (int) $credit->public_id,
            'amount' => (float) $credit->amount,
            'balance' => (float) $credit->balance,
            'updated_at' => $this->getTimestamp($credit->updated_at),
            'archived_at' => $this->getTimestamp($credit->deleted_at),
            'is_deleted' => (bool) $credit->is_deleted,
            'organisation_key' => $this->organisation->organisation_key,
            'credit_date' => $credit->credit_date,
            'credit_number' => $credit->credit_number,
            'private_notes' => $credit->private_notes,
        ];
    }
}