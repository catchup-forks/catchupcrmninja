<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use App\Models\PaymentTerm;
use App\Ninja\Repositories\BaseRepository;

class PaymentTermRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\PaymentTerm';
    }

    public function find($organisationId = 0)
    {
        return DB::table('payment_terms')
                //->where('payment_terms.organisation_id', '=', $organisationId)
                ->where('payment_terms.deleted_at', '=', null)
                ->select('payment_terms.public_id', 'payment_terms.name', 'payment_terms.num_days', 'payment_terms.deleted_at');
    }
}
