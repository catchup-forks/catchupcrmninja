<?php namespace App\Ninja\Repositories;

use DB;
use Cache;
use App\Ninja\Repositories\BaseRepository;
use App\Models\Relation;
use App\Models\Contact;
use App\Models\Activity;
use App\Events\RelationWasCreated;
use App\Events\RelationWasUpdated;

class RelationRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Relation';
    }

    public function all()
    {
        return Relation::scope()
                ->with('user', 'contacts', 'country')
                ->withTrashed()
                ->where('is_deleted', '=', false)
                ->get();
    }

    public function find($filter = null)
    {
        $query = DB::table('relations')
                    ->join('organisations', 'organisations.id', '=', 'relations.organisation_id')
                    ->join('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->where('relations.organisation_id', '=', \Auth::user()->organisation_id)
                    ->where('contacts.is_primary', '=', true)
                    ->where('contacts.deleted_at', '=', null)
                    ->select(
                        DB::raw('COALESCE(relations.currency_id, organisations.currency_id) currency_id'),
                        DB::raw('COALESCE(relations.country_id, organisations.country_id) country_id'),
                        'relations.public_id',
                        'relations.name',
                        'contacts.first_name',
                        'contacts.last_name',
                        'relations.balance',
                        'relations.last_login',
                        'relations.created_at',
                        'relations.work_phone',
                        'contacts.email',
                        'relations.deleted_at',
                        'relations.is_deleted',
                        'relations.user_id'
                    );

        if (!\Session::get('show_trash:relation')) {
            $query->where('relations.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('relations.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }
    
    public function save($data)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if (!$publicId || $publicId == '-1') {
            $relation = Relation::createNew();
        } else {
            $relation = Relation::scope($publicId)->with('contacts')->firstOrFail();
        }

        // convert currency code to id
        if (isset($data['currency_code'])) {
            $currencyCode = strtolower($data['currency_code']);
            $currency = Cache::get('currencies')->filter(function($item) use ($currencyCode) {
                return strtolower($item->code) == $currencyCode;
            })->first();
            if ($currency) {
                $data['currency_id'] = $currency->id;
            }
        }

        $relation->fill($data);
        $relation->save();

        /*
        if ( ! isset($data['contact']) && ! isset($data['contacts'])) {
            return $relation;
        }
        */
        
        $first = true;
        $contacts = isset($data['contact']) ? [$data['contact']] : $data['contacts'];
        $contactIds = [];

        foreach ($contacts as $contact) {
            $contact = $relation->addContact($contact, $first);
            $contactIds[] = $contact->public_id;
            $first = false;
        }

        foreach ($relation->contacts as $contact) {
            if (!in_array($contact->public_id, $contactIds)) {
                $contact->delete();
            }
        }

        if (!$publicId || $publicId == '-1') {
            event(new RelationWasCreated($relation));
        } else {
            event(new RelationWasUpdated($relation));
        }

        return $relation;
    }
}
