<?php namespace App\Ninja\Repositories;

use Auth;
use Request;
use Session;
use Utils;
use DB;
use URL;
use stdClass;
use Validator;
use Schema;
use App\Models\OrganisationGateway;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Relation;
use App\Models\Language;
use App\Models\Contact;
use App\Models\Organisation;
use App\Models\User;
use App\Models\UserOrganisation;
use App\Models\OrganisationToken;

class OrganisationRepository
{
    public function create($firstName = '', $lastName = '', $email = '', $password = '')
    {
        $organisation = new Organisation();
        $organisation->ip = Request::getClientIp();
        $organisation->organisation_key = str_random(RANDOM_KEY_LENGTH);

        // Track referal code
        if ($referralCode = Session::get(SESSION_REFERRAL_CODE)) {
            if ($user = User::whereReferralCode($referralCode)->first()) {
                $organisation->referral_user_id = $user->id;
            }
        }

        if ($locale = Session::get(SESSION_LOCALE)) {
            if ($language = Language::whereLocale($locale)->first()) {
                $organisation->language_id = $language->id;
            }
        }

        $organisation->save();

        $user = new User();
        if (!$firstName && !$lastName && !$email && !$password) {
            $user->password = str_random(RANDOM_KEY_LENGTH);
            $user->username = str_random(RANDOM_KEY_LENGTH);
        } else {
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->email = $user->username = $email;
            if (!$password) {
                $password = str_random(RANDOM_KEY_LENGTH);
            }
            $user->password = bcrypt($password);
        }

        $user->confirmed = !Utils::isNinja();
        $user->registered = !Utils::isNinja() || $email;

        if (!$user->confirmed) {
            $user->confirmation_code = str_random(RANDOM_KEY_LENGTH);
        }

        $organisation->users()->save($user);

        return $organisation;
    }

    public function getSearchData($organisation)
    {
        $data = $this->getOrganisationSearchData($organisation);

        $data['navigation'] = $this->getNavigationSearchData();

        return $data;
    }

    private function getOrganisationSearchData($organisation)
    {
        $data = [
            'relations' => [],
            'contacts' => [],
            'invoices' => [],
            'quotes' => [],
        ];

        // include custom relation fields in search
        if ($organisation->custom_relation_label1) {
            $data[$organisation->custom_relation_label1] = [];
        }
        if ($organisation->custom_relation_label2) {
            $data[$organisation->custom_relation_label2] = [];
        }

        $relations = Relation::scope()
                    ->with('contacts', 'invoices')
                    ->get();

        foreach ($relations as $relation) {
            if ($relation->name) {
                $data['relations'][] = [
                    'value' => $relation->name,
                    'tokens' => $relation->name,
                    'url' => $relation->present()->url,
                ];
            } 

            if ($relation->custom_value1) {
                $data[$organisation->custom_relation_label1][] = [
                    'value' => "{$relation->custom_value1}: " . $relation->getDisplayName(),
                    'tokens' => $relation->custom_value1,
                    'url' => $relation->present()->url,
                ];
            }           
            if ($relation->custom_value2) {
                $data[$organisation->custom_relation_label2][] = [
                    'value' => "{$relation->custom_value2}: " . $relation->getDisplayName(),
                    'tokens' => $relation->custom_value2,
                    'url' => $relation->present()->url,
                ];
            }

            foreach ($relation->contacts as $contact) {
                if ($contact->getFullName()) {
                    $data['contacts'][] = [
                        'value' => $contact->getDisplayName(),
                        'tokens' => $contact->getDisplayName(),
                        'url' => $relation->present()->url,
                    ];
                }
                if ($contact->email) {
                    $data['contacts'][] = [
                        'value' => $contact->email,
                        'tokens' => $contact->email,
                        'url' => $relation->present()->url,
                    ];
                }
            }

            foreach ($relation->invoices as $invoice) {
                $entityType = $invoice->getEntityType();
                $data["{$entityType}s"][] = [
                    'value' => $invoice->getDisplayName() . ': ' . $relation->getDisplayName(),
                    'tokens' => $invoice->getDisplayName() . ': ' . $relation->getDisplayName(),
                    'url' => $invoice->present()->url,
                ];
            }
        }

        return $data;
    }

    private function getNavigationSearchData()
    {
        $entityTypes = [
            ENTITY_INVOICE,
            ENTITY_RELATION,
            ENTITY_QUOTE,
            ENTITY_TASK,
            ENTITY_EXPENSE,
            ENTITY_RECURRING_INVOICE,
            ENTITY_PAYMENT,
            ENTITY_CREDIT
        ];

        foreach ($entityTypes as $entityType) {
            $features[] = [
                "new_{$entityType}",
                "/{$entityType}s/create",
            ];
            $features[] = [
                "list_{$entityType}s",
                "/{$entityType}s",
            ];
        }

        $features[] = ['dashboard', '/dashboard'];
        $features[] = ['customize_design', '/settings/customize_design'];
        $features[] = ['new_tax_rate', '/tax_rates/create'];
        $features[] = ['new_product', '/products/create'];
        $features[] = ['new_user', '/users/create'];
        $features[] = ['custom_fields', '/settings/invoice_settings'];	

        $settings = array_merge(Organisation::$basicSettings, Organisation::$advancedSettings);

        foreach ($settings as $setting) {
            $features[] = [
                $setting,
                "/settings/{$setting}",
            ];
        }

        foreach ($features as $feature) {
            $data[] = [
                'value' => trans('texts.' . $feature[0]),
                'tokens' => trans('texts.' . $feature[0]),
                'url' => URL::to($feature[1])
            ];
        }

        return $data;
    }

    public function enableProPlan()
    {
        if (Auth::user()->isPro() && ! Auth::user()->isTrial()) {
            return false;
        }
        
        $organisation = Auth::user()->organisation;
        $relation = $this->getNinjaRelation($organisation);
        $invitation = $this->createNinjaInvoice($relation, $organisation);

        return $invitation;
    }

    public function createNinjaInvoice($relation, $relationOrganisation)
    {
        $organisation = $this->getNinjaAccount();
        $lastInvoice = Invoice::withTrashed()->whereOrganisationId($organisation->id)->orderBy('public_id', 'DESC')->first();
        $publicId = $lastInvoice ? ($lastInvoice->public_id + 1) : 1;
        $invoice = new Invoice();
        $invoice->organisation_id = $organisation->id;
        $invoice->user_id = $organisation->users()->first()->id;
        $invoice->public_id = $publicId;
        $invoice->relation_id = $relation->id;
        $invoice->invoice_number = $organisation->getNextInvoiceNumber($invoice);
        $invoice->invoice_date = $relationOrganisation->getRenewalDate();
        $invoice->amount = PRO_PLAN_PRICE;
        $invoice->balance = PRO_PLAN_PRICE;
        $invoice->save();

        $item = new InvoiceItem();
        $item->organisation_id = $organisation->id;
        $item->user_id = $organisation->users()->first()->id;
        $item->public_id = $publicId;
        $item->qty = 1;
        $item->cost = PRO_PLAN_PRICE;
        $item->notes = trans('texts.pro_plan_description');
        $item->product_key = trans('texts.pro_plan_product');
        $invoice->invoice_items()->save($item);

        $invitation = new Invitation();
        $invitation->organisation_id = $organisation->id;
        $invitation->user_id = $organisation->users()->first()->id;
        $invitation->public_id = $publicId;
        $invitation->invoice_id = $invoice->id;
        $invitation->contact_id = $relation->contacts()->first()->id;
        $invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
        $invitation->save();

        return $invitation;
    }

    public function getNinjaAccount()
    {
        $organisation = Organisation::whereOrganisationKey(NINJA_ORGANISATION_KEY)->first();

        if ($organisation) {
            return $organisation;
        } else {
            $organisation = new Organisation();
            $organisation->name = 'Invoice Ninja';
            $organisation->work_email = 'contact@invoiceninja.com';
            $organisation->work_phone = '(800) 763-1948';
            $organisation->organisation_key = NINJA_ORGANISATION_KEY;
            $organisation->save();

            $random = str_random(RANDOM_KEY_LENGTH);
            $user = new User();
            $user->registered = true;
            $user->confirmed = true;
            $user->email = 'contact@invoiceninja.com';
            $user->password = $random;
            $user->username = $random;
            $user->first_name = 'Invoice';
            $user->last_name = 'Ninja';
            $user->notify_sent = true;
            $user->notify_paid = true;
            $organisation->users()->save($user);

            $OrganisationGateway = new OrganisationGateway();
            $OrganisationGateway->user_id = $user->id;
            $OrganisationGateway->gateway_id = NINJA_GATEWAY_ID;
            $OrganisationGateway->public_id = 1;
            $OrganisationGateway->setConfig(json_decode(env(NINJA_GATEWAY_CONFIG)));
            $organisation->organisation_gateways()->save($OrganisationGateway);
        }

        return $organisation;
    }

    public function getNinjaRelation($organisation)
    {
        $organisation->load('users');
        $ninjaOrganisation = $this->getNinjaAccount();
        $relation = Relation::whereOrganisationId($ninjaOrganisation->id)->wherePublicId($organisation->id)->first();

        if (!$relation) {
            $relation = new Relation();
            $relation->public_id = $organisation->id;
            $relation->user_id = $ninjaOrganisation->users()->first()->id;
            $relation->currency_id = 1;
            foreach (['name', 'address1', 'housenumber', 'city', 'state', 'postal_code', 'country_id', 'work_phone', 'language_id'] as $field) {
                $relation->$field = $organisation->$field;
            }
            $ninjaOrganisation->relations()->save($relation);

            $contact = new Contact();
            $contact->user_id = $ninjaOrganisation->users()->first()->id;
            $contact->organisation_id = $ninjaOrganisation->id;
            $contact->public_id = $organisation->id;
            $contact->is_primary = true;
            foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
                $contact->$field = $organisation->users()->first()->$field;
            }
            $relation->contacts()->save($contact);
        }

        return $relation;
    }

    public function findByKey($key)
    {
        $organisation = Organisation::whereOrganisationKey($key)
                    ->with('relations.invoices.invoice_items', 'relations.contacts')
                    ->firstOrFail();

        return $organisation;
    }

    public function unlinkUserFromOauth($user)
    {
        $user->oauth_provider_id = null;
        $user->oauth_user_id = null;
        $user->save();
    }

    public function updateUserFromOauth($user, $firstName, $lastName, $email, $providerId, $oauthUserId)
    {
        if (!$user->registered) {
            $rules = ['email' => 'email|required|unique:users,email,'.$user->id.',id'];
            $validator = Validator::make(['email' => $email], $rules);
            if ($validator->fails()) {
                $messages = $validator->messages();
                return $messages->first('email');
            }

            $user->email = $email;
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->registered = true;

            $user->organisation->startTrial();
        }

        $user->oauth_provider_id = $providerId;
        $user->oauth_user_id = $oauthUserId;
        $user->save();

        return true;
    }

    public function registerNinjaUser($user)
    {
        if ($user->email == TEST_USERNAME) {
            return false;
        }

        $url = (Utils::isNinjaDev() ? SITE_URL : NINJA_APP_URL) . '/signup/register';
        $data = '';
        $fields = [
            'first_name' => urlencode($user->first_name),
            'last_name' => urlencode($user->last_name),
            'email' => urlencode($user->email),
        ];

        foreach ($fields as $key => $value) {
            $data .= $key.'='.$value.'&';
        }
        rtrim($data, '&');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function findUserByOauth($providerId, $oauthUserId)
    {
        return User::where('oauth_user_id', $oauthUserId)
                    ->where('oauth_provider_id', $providerId)
                    ->first();
    }

    public function findUsers($user, $with = null)
    {
        $organisations = $this->findUserOrganisations($user->id);

        if ($organisations) {
            return $this->getUserOrganisations($organisations, $with);
        } else {
            return [$user];
        }
    }

    public function findUserOrganisations($userId1, $userId2 = false)
    {
        if (!Schema::hasTable('user_organisations')) {
            return false;
        }

        $query = UserOrganisation::where('user_id1', '=', $userId1)
                                ->orWhere('user_id2', '=', $userId1)
                                ->orWhere('user_id3', '=', $userId1)
                                ->orWhere('user_id4', '=', $userId1)
                                ->orWhere('user_id5', '=', $userId1);

        if ($userId2) {
            $query->orWhere('user_id1', '=', $userId2)
                    ->orWhere('user_id2', '=', $userId2)
                    ->orWhere('user_id3', '=', $userId2)
                    ->orWhere('user_id4', '=', $userId2)
                    ->orWhere('user_id5', '=', $userId2);
        }

        return $query->first(['id', 'user_id1', 'user_id2', 'user_id3', 'user_id4', 'user_id5']);
    }

    public function getUserOrganisations($record, $with = null)
    {
        if (!$record) {
            return false;
        }

        $userIds = [];
        for ($i=1; $i<=5; $i++) {
            $field = "user_id$i";
            if ($record->$field) {
                $userIds[] = $record->$field;
            }
        }

        $users = User::with('organisation')
                    ->whereIn('id', $userIds);

        if ($with) {
            $users->with($with);
        }
        
        return $users->get();
    }

    public function prepareUsersData($record)
    {
        if (!$record) {
            return false;
        }

        $users = $this->getUserOrganisations($record);

        $data = [];
        foreach ($users as $user) {
            $item = new stdClass();
            $item->id = $record->id;
            $item->user_id = $user->id;
            $item->user_name = $user->getDisplayName();
            $item->organisation_id = $user->organisation->id;
            $item->account_name = $user->organisation->getDisplayName();
            $item->pro_plan_paid = $user->organisation->pro_plan_paid;
            $item->logo_path = $user->organisation->hasLogo() ? $user->organisation->getLogoPath() : null;
            $data[] = $item;
        }

        return $data;
    }

    public function loadOrganisations($userId) {
        $record = self::findUserOrganisations($userId);
        return self::prepareUsersData($record);
    }

    public function syncOrganisations($userId, $proPlanPaid) {
        $users = self::loadOrganisations($userId);
        self::syncUserOrganisations($users, $proPlanPaid);
    }

    public function syncUserOrganisations($users, $proPlanPaid = false) {
        if (!$users) {
            return;
        }

        if (!$proPlanPaid) {
            foreach ($users as $user) {
                if ($user->pro_plan_paid && $user->pro_plan_paid != '0000-00-00') {
                    $proPlanPaid = $user->pro_plan_paid;
                    break;
                }
            }
        }

        if (!$proPlanPaid) {
            return;
        }

        $organisationIds = [];
        foreach ($users as $user) {
            if ($user->pro_plan_paid != $proPlanPaid) {
                $organisationIds[] = $user->organisation_id;
            }
        }

        if (count($organisationIds)) {
            DB::table('organisations')
                ->whereIn('id', $organisationIds)
                ->update(['pro_plan_paid' => $proPlanPaid]);
        }
    }

    public function associateOrganisations($userId1, $userId2) {

        $record = self::findUserOrganisations($userId1, $userId2);

        if ($record) {
            foreach ([$userId1, $userId2] as $userId) {
                if (!$record->hasUserId($userId)) {
                    $record->setUserId($userId);
                }
            }
        } else {
            $record = new UserOrganisation();
            $record->user_id1 = $userId1;
            $record->user_id2 = $userId2;
        }

        $record->save();

        $users = self::prepareUsersData($record);
        self::syncUserOrganisations($users);

        return $users;
    }

    public function unlinkOrganisation($organisation) {
        foreach ($organisation->users as $user) {
            if ($userOrganisation = self::findUserOrganisations($user->id)) {
                $userOrganisation->removeUserId($user->id);
                $userOrganisation->save();
            }
        }
    }

    public function unlinkUser($userOrganisationId, $userId) {
        $userOrganisation = UserOrganisation::whereId($userOrganisationId)->first();
        if ($userOrganisation->hasUserId($userId)) {
            $userOrganisation->removeUserId($userId);
            $userOrganisation->save();
        }
    }

    public function findWithReminders()
    {
        return Organisation::whereRaw('enable_reminder1 = 1 OR enable_reminder2 = 1 OR enable_reminder3 = 1')->get();
    }

    public function getReferralCode()
    {
        do {
            $code = strtoupper(str_random(8));
            $match = User::whereReferralCode($code)
                        ->withTrashed()
                        ->first();
        } while ($match);
        
        return $code;
    }

    public function createTokens($user, $name)
    {
        $name = trim($name) ?: 'TOKEN';
        $users = $this->findUsers($user);

        foreach ($users as $user) {
            if ($token = OrganisationToken::whereUserId($user->id)->whereName($name)->first()) {
                continue;
            }

            $token = OrganisationToken::createNew($user);
            $token->name = $name;
            $token->token = str_random(RANDOM_KEY_LENGTH);
            $token->save();
        }
    }

    public function getUserOrganisationId($organisation)
    {
        $user = $organisation->users()->first();
        $userOrganisation = $this->findUserOrganisations($user->id);

        return $userOrganisation ? $userOrganisation->id : false;
    }

    public function save($data, $organisation)
    {
        $organisation->fill($data);
        $organisation->save();
    }
}
