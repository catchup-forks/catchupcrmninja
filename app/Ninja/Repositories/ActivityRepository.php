<?php namespace App\Ninja\Repositories;

use DB;
use Auth;
use Utils;
use Request;
use App\Models\Activity;
use App\Models\Relation;

class ActivityRepository
{
    public function create($entity, $activityTypeId, $balanceChange = 0, $paidToDateChange = 0, $altEntity = null)
    {
        if ($entity instanceof Relation) {
            $relation = $entity;
        } elseif ($entity instanceof Invitation) {
            $relation = $entity->invoice->relation;
        } else {
            $relation = $entity->relation;
        }

        // init activity and copy over context
        $activity = self::getBlank($altEntity ?: $relation);
        $activity = Utils::copyContext($activity, $entity);
        $activity = Utils::copyContext($activity, $altEntity);

        $activity->relation_id = $relation->id;
        $activity->activity_type_id = $activityTypeId;
        $activity->adjustment = $balanceChange;
        $activity->balance = $relation->balance + $balanceChange;

        $keyField = $entity->getKeyField();
        $activity->$keyField = $entity->id;

        $activity->ip = Request::getClientIp();
        $activity->save();

        $relation->updateBalances($balanceChange, $paidToDateChange);

        return $activity;
    }

    private function getBlank($entity)
    {
        $activity = new Activity();

        if (Auth::check() && Auth::user()->organisation_id == $entity->organisation_id) {
            $activity->user_id = Auth::user()->id;
            $activity->organisation_id = Auth::user()->organisation_id;
        } else {
            $activity->user_id = $entity->user_id;
            $activity->organisation_id = $entity->organisation_id;

            if ( ! $entity instanceof Invitation) {
                $activity->is_system = true;
            }
        }

        $activity->token_id = session('token_id');

        return $activity;
    }

    public function findByRelationId($relationId)
    {
        return DB::table('activities')
                    ->join('organisations', 'organisations.id', '=', 'activities.organisation_id')
                    ->join('users', 'users.id', '=', 'activities.user_id')
                    ->join('relations', 'relations.id', '=', 'activities.relation_id')
                    ->leftJoin('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'activities.invoice_id')
                    ->leftJoin('payments', 'payments.id', '=', 'activities.payment_id')
                    ->leftJoin('credits', 'credits.id', '=', 'activities.credit_id')
                    ->where('relations.id', '=', $relationId)
                    ->where('contacts.is_primary', '=', 1)
                    ->whereNull('contacts.deleted_at')
                    ->select(
                        DB::raw('COALESCE(relations.currency_id, organisations.currency_id) currency_id'),
                        DB::raw('COALESCE(relations.country_id, organisations.country_id) country_id'),
                        'activities.id',
                        'activities.created_at',
                        'activities.contact_id',
                        'activities.activity_type_id',
                        'activities.is_system',
                        'activities.balance',
                        'activities.adjustment',
                        'users.first_name as user_first_name',
                        'users.last_name as user_last_name',
                        'users.email as user_email',
                        'invoices.invoice_number as invoice',
                        'invoices.public_id as invoice_public_id',
                        'invoices.is_recurring',
                        'relations.name as relation_name',
                        'relations.public_id as relation_public_id',
                        'contacts.id as contact',
                        'contacts.first_name as first_name',
                        'contacts.last_name as last_name',
                        'contacts.email as email',
                        'payments.transaction_reference as payment',
                        'credits.amount as credit'
                    );
    }

}