<?php namespace App\Models;

use Utils;
use DB;
use Carbon;
use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Relation extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    protected $presenter = 'App\Ninja\Presenters\RelationPresenter';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name',
        'id_number',
        'vat_number',
        'work_phone',
        'custom_value1',
        'custom_value2',
        'address1',
        'housenumber',
        'city',
        'state',
        'postal_code',
        'country_id',
        'private_notes',
        'size_id',
        'industry_id',
        'currency_id',
        'language_id',
        'payment_terms',
        'website',
    ];

    public static $fieldName = 'name';
    public static $fieldPhone = 'work_phone';
    public static $fieldAddress1 = 'address1';
    public static $fieldAddress2 = 'housenumber';
    public static $fieldCity = 'city';
    public static $fieldState = 'state';
    public static $fieldPostalCode = 'postal_code';
    public static $fieldNotes = 'notes';
    public static $fieldCountry = 'country';

    public static function getImportColumns()
    {
        return [
            Relation::$fieldName,
            Relation::$fieldPhone,
            Relation::$fieldAddress1,
            Relation::$fieldAddress2,
            Relation::$fieldCity,
            Relation::$fieldState,
            Relation::$fieldPostalCode,
            Relation::$fieldCountry,
            Relation::$fieldNotes,
            Contact::$fieldFirstName,
            Contact::$fieldLastName,
            Contact::$fieldPhone,
            Contact::$fieldEmail,
        ];
    }

    public static function getImportMap()
    {
        return [
            'first' => 'first_name',
            'last' => 'last_name',
            'email' => 'email',
            'mobile|phone' => 'phone',
            'name|organization' => 'name',
            'street2|housenumber' => 'housenumber',
            'street|address|address1' => 'address1',
            'city' => 'city',
            'state|province' => 'state',
            'zip|postal|code' => 'postal_code',
            'country' => 'country',
            'note' => 'notes',
        ];
    }

    public function organisation()
    {
        return $this->belongsTo('App\Models\Organisation');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function invoices()
    {
        return $this->hasMany('App\Models\Invoice');
    }

    public function payments()
    {
        return $this->hasMany('App\Models\Payment');
    }

    public function contacts()
    {
        return $this->hasMany('App\Models\Contact');
    }

    public function country()
    {
        return $this->belongsTo('App\Models\Country');
    }

    public function currency()
    {
        return $this->belongsTo('App\Models\Currency');
    }

    public function language()
    {
        return $this->belongsTo('App\Models\Language');
    }

    public function size()
    {
        return $this->belongsTo('App\Models\Size');
    }

    public function industry()
    {
        return $this->belongsTo('App\Models\Industry');
    }

    public function credits()
    {
        return $this->hasMany('App\Models\Credit');
    }

    public function expenses()
    {
        return $this->hasMany('App\Models\Expense','relation_id','id')->withTrashed();
    }

    public function addContact($data, $isPrimary = false)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ($publicId && $publicId != '-1') {
            $contact = Contact::scope($publicId)->firstOrFail();
        } else {
            $contact = Contact::createNew();
            $contact->send_invoice = true;
        }
        
        if (!Utils::isPro() || $this->organisation->enable_portal_password){
            if(!empty($data['password']) && $data['password']!='-%unchanged%-'){
                $contact->password = bcrypt($data['password']);
            } else if(empty($data['password'])){
                $contact->password = null;
            }
        }
            
        $contact->fill($data);
        $contact->is_primary = $isPrimary;

        return $this->contacts()->save($contact);
    }

    public function updateBalances($balanceAdjustment, $paidToDateAdjustment)
    {
        if ($balanceAdjustment === 0 && $paidToDateAdjustment === 0) {
            return;
        }

        $this->balance = $this->balance + $balanceAdjustment;
        $this->paid_to_date = $this->paid_to_date + $paidToDateAdjustment;
        
        $this->save();
    }

    public function getRoute()
    {
        return "/relations/{$this->public_id}";
    }

    public function getTotalCredit()
    {
        return DB::table('credits')
                ->where('relation_id', '=', $this->id)
                ->whereNull('deleted_at')
                ->sum('balance');
    }

    public function getName()
    {
        return $this->name;
    }
    
    public function getDisplayName()
    {
        if ($this->name) {
            return $this->name;
        }
        
        if ( ! count($this->contacts)) {
            return '';
        }

        $contact = $this->contacts[0];
        return $contact->getDisplayName();
    }

    public function getCityState()
    {
        $swap = $this->country && $this->country->swap_postal_code;
        return Utils::cityStateZip($this->city, $this->state, $this->postal_code, $swap);
    }

    public function getEntityType()
    {
        return ENTITY_RELATION;
    }

    public function hasAddress()
    {
        $fields = [
            'address1',
            'housenumber',
            'city',
            'state',
            'postal_code',
            'country_id',
        ];

        foreach ($fields as $field) {
            if ($this->$field) {
                return true;
            }
        }

        return false;
    }

    public function getDateCreated()
    {
        if ($this->created_at == '0000-00-00 00:00:00') {
            return '---';
        } else {
            return $this->created_at->format('m/d/y h:i a');
        }
    }


    public function getGatewayToken()
    {
        $this->organisation->load('organisation_gateways');

        if (!count($this->organisation->organisation_gateways)) {
            return false;
        }

        $OrganisationGateway = $this->organisation->getGatewayConfig(GATEWAY_STRIPE);
        
        if (!$OrganisationGateway) {
            return false;
        }

        $token = OrganisationGatewayToken::where('relation_id', '=', $this->id)
                    ->where('account_gateway_id', '=', $OrganisationGateway->id)->first();

        return $token ? $token->token : false;
    }

    public function getGatewayLink()
    {
        $token = $this->getGatewayToken();
        return $token ? "https://dashboard.stripe.com/customers/{$token}" : false;
    }

    public function getAmount()
    {
        return $this->balance + $this->paid_to_date;
    }

    public function getCurrencyId()
    {
        if ($this->currency_id) {
            return $this->currency_id;
        }

        if (!$this->organisation) {
            $this->load('organisation');
        }

        return $this->organisation->currency_id ?: DEFAULT_CURRENCY;
    }

    public function getCounter($isQuote)
    {
        return $isQuote ? $this->quote_number_counter : $this->invoice_number_counter;
    }

    public function markLoggedIn()
    {
        $this->last_login = Carbon::now()->toDateTimeString();
        $this->save();
    }
}

Relation::creating(function ($relation) {
    $relation->setNullValues();
});

Relation::updating(function ($relation) {
    $relation->setNullValues();
});