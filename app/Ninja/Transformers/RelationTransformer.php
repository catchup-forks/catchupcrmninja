<?php namespace App\Ninja\Transformers;

use App\Models\Organisation;
use App\Models\Relation;
use App\Models\Contact;
use League\Fractal;

/**
 * @SWG\Definition(definition="Relation", @SWG\Xml(name="Relation"))
 */

class RelationTransformer extends EntityTransformer
{
    /**
    * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="balance", type="float", example=10, readOnly=true)
    * @SWG\Property(property="paid_to_date", type="float", example=10, readOnly=true)
    * @SWG\Property(property="user_id", type="integer", example=1)
    * @SWG\Property(property="organisation_key", type="string", example="123456")
    * @SWG\Property(property="updated_at", type="timestamp", example="")
    * @SWG\Property(property="archived_at", type="timestamp", example="1451160233")
    * @SWG\Property(property="address1", type="string", example="10 Main St.")
    * @SWG\Property(property="housenumber", type="string", example="1st Floor")
    * @SWG\Property(property="city", type="string", example="New York")
    * @SWG\Property(property="state", type="string", example="NY")
    * @SWG\Property(property="postal_code", type="string", example=10010)
    * @SWG\Property(property="country_id", type="integer", example=840)
    * @SWG\Property(property="work_phone", type="string", example="(212) 555-1212")
    * @SWG\Property(property="private_notes", type="string", example="Notes...")
    * @SWG\Property(property="last_login", type="date-time", example="2016-01-01 12:10:00")
    * @SWG\Property(property="website", type="string", example="http://www.example.com")
    * @SWG\Property(property="industry_id", type="integer", example=1)
    * @SWG\Property(property="size_id", type="integer", example=1)
    * @SWG\Property(property="is_deleted", type="boolean", example=false)
    * @SWG\Property(property="payment_terms", type="", example=30)
    * @SWG\Property(property="custom_value1", type="string", example="Value")
    * @SWG\Property(property="custom_value2", type="string", example="Value")
    * @SWG\Property(property="vat_number", type="string", example="123456")
    * @SWG\Property(property="id_number", type="string", example="123456")
    * @SWG\Property(property="language_id", type="integer", example=1)
    */

    protected $defaultIncludes = [
        'contacts',
    ];

    protected $availableIncludes = [
        'invoices',
        'credits',
        'expenses',
    ];
    
    public function includeContacts(Relation $relation)
    {
        $transformer = new ContactTransformer($this->organisation, $this->serializer);
        return $this->includeCollection($relation->contacts, $transformer, ENTITY_CONTACT);
    }

    public function includeInvoices(Relation $relation)
    {
        $transformer = new InvoiceTransformer($this->organisation, $this->serializer);
        return $this->includeCollection($relation->invoices, $transformer, ENTITY_INVOICE);
    }

    public function includeCredits(Relation $relation)
    {
        $transformer = new CreditTransformer($this->organisation, $this->serializer);
        return $this->includeCollection($relation->credits, $transformer, ENTITY_CREDIT);
    }

    public function includeExpenses(Relation $relation)
    {
        $transformer = new ExpenseTransformer($this->organisation, $this->serializer);
        return $this->includeCollection($relation->expenses, $transformer, ENTITY_EXPENSE);
    }


    public function transform(Relation $relation)
    {
        return [
            'id' => (int) $relation->public_id,
            'name' => $relation->name,
            'balance' => (float) $relation->balance,
            'paid_to_date' => (float) $relation->paid_to_date,
            'user_id' => (int) $relation->user->public_id + 1,
            'organisation_key' => $this->organisation->organisation_key,
            'updated_at' => $this->getTimestamp($relation->updated_at),
            'archived_at' => $this->getTimestamp($relation->deleted_at),
            'address1' => $relation->address1,
            'housenumber' => $relation->housenumber,
            'city' => $relation->city,
            'state' => $relation->state,
            'postal_code' => $relation->postal_code,
            'country_id' => (int) $relation->country_id,
            'work_phone' => $relation->work_phone,
            'private_notes' => $relation->private_notes,
            'last_login' => $relation->last_login,
            'website' => $relation->website,
            'industry_id' => (int) $relation->industry_id,
            'size_id' => (int) $relation->size_id,
            'is_deleted' => (bool) $relation->is_deleted,
            'payment_terms' => (int) $relation->payment_terms,
            'vat_number' => $relation->vat_number,
            'id_number' => $relation->id_number,
            'language_id' => (int) $relation->language_id,
            'currency_id' => (int) $relation->currency_id,
            'custom_value1' => $relation->custom_value1,
            'custom_value2' => $relation->custom_value2,
        ];
    }
}