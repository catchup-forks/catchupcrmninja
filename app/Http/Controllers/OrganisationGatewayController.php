<?php namespace App\Http\Controllers;

use Auth;
use Datatable;
use DB;
use Input;
use Redirect;
use Session;
use View;
use Validator;
use stdClass;
use URL;
use Utils;
use App\Models\Gateway;
use App\Models\Organisation;
use App\Models\OrganisationGateway;

use App\Ninja\Repositories\OrganisationRepository;
use App\Services\OrganisationGatewayService;

class OrganisationGatewayController extends BaseController
{
    protected $OrganisationGatewayService;

    public function __construct(OrganisationGatewayService $OrganisationGatewayService)
    {
        //parent::__construct();

        $this->OrganisationGatewayService = $OrganisationGatewayService;
    }

    public function index()
    {
        return Redirect::to('settings/' . ORGANISATION_PAYMENTS);
    }

    public function getDatatable()
    {
        return $this->OrganisationGatewayService->getDatatable(Auth::user()->organisation_id);
    }

    public function edit($publicId)
    {
        $OrganisationGateway = OrganisationGateway::scope($publicId)->firstOrFail();
        $config = $OrganisationGateway->getConfig();

        foreach ($config as $field => $value) {
            $config->$field = str_repeat('*', strlen($value));
        }

        $data = self::getViewModel($OrganisationGateway);
        $data['url'] = 'gateways/'.$publicId;
        $data['method'] = 'PUT';
        $data['title'] = trans('texts.edit_gateway') . ' - ' . $OrganisationGateway->gateway->name;
        $data['config'] = $config;
        $data['hiddenFields'] = Gateway::$hiddenFields;
        $data['paymentTypeId'] = $OrganisationGateway->getPaymentType();
        $data['selectGateways'] = Gateway::where('id', '=', $OrganisationGateway->gateway_id)->get();


        return View::make('organisations.account_gateway', $data);
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    public function store()
    {
        return $this->save();
    }

    /**
     * Displays the form for organisation creation
     *
     */
    public function create()
    {
        $data = self::getViewModel();
        $data['url'] = 'gateways';
        $data['method'] = 'POST';
        $data['title'] = trans('texts.add_gateway');
        $data['selectGateways'] = Gateway::where('payment_library_id', '=', 1)
                                    ->where('id', '!=', GATEWAY_PAYPAL_EXPRESS)
                                    ->where('id', '!=', GATEWAY_BITPAY)
                                    ->where('id', '!=', GATEWAY_GOCARDLESS)
                                    ->where('id', '!=', GATEWAY_DWOLLA)
                                    ->orderBy('name')->get();
        $data['hiddenFields'] = Gateway::$hiddenFields;

        if ( ! \Request::secure() && ! Utils::isNinjaDev()) {
            Session::flash('warning', trans('texts.enable_https'));
        }

        return View::make('organisations.account_gateway', $data);
    }

    private function getViewModel($OrganisationGateway = false)
    {
        $selectedCards = $OrganisationGateway ? $OrganisationGateway->accepted_credit_cards : 0;
        $organisation = Auth::user()->organisation;

        $paymentTypes = [];
        foreach (Gateway::$paymentTypes as $type) {
            if ($OrganisationGateway || !$organisation->getGatewayByType($type)) {
                $paymentTypes[$type] = trans('texts.'.strtolower($type));

                if ($type == PAYMENT_TYPE_BITCOIN) {
                    $paymentTypes[$type] .= ' - BitPay';
                }
                if ($type == PAYMENT_TYPE_DIRECT_DEBIT) {
                    $paymentTypes[$type] .= ' - GoCardless';
                }
            }
        }

        $creditCardsArray = unserialize(CREDIT_CARDS);
        $creditCards = [];
        foreach ($creditCardsArray as $card => $name) {
            if ($selectedCards > 0 && ($selectedCards & $card) == $card) {
                $creditCards[$name['text']] = ['value' => $card, 'data-imageUrl' => asset($name['card']), 'checked' => 'checked'];
            } else {
                $creditCards[$name['text']] = ['value' => $card, 'data-imageUrl' => asset($name['card'])];
            }
        }

        $organisation->load('organisation_gateways');
        $currentGateways = $organisation->organisation_gateways;
        $gateways = Gateway::where('payment_library_id', '=', 1)->orderBy('name')->get();

        foreach ($gateways as $gateway) {
            $fields = $gateway->getFields();
            asort($fields);
            $gateway->fields = $fields;
            if ($OrganisationGateway && $OrganisationGateway->gateway_id == $gateway->id) {
                $OrganisationGateway->fields = $gateway->fields;
            }
        }

        $tokenBillingOptions = [];
        for ($i=1; $i<=4; $i++) {
            $tokenBillingOptions[$i] = trans("texts.token_billing_{$i}");
        }

        return [
            'paymentTypes' => $paymentTypes,
            'organisation' => $organisation,
            'OrganisationGateway' => $OrganisationGateway,
            'config' => false,
            'gateways' => $gateways,
            'creditCardTypes' => $creditCards,
            'tokenBillingOptions' => $tokenBillingOptions,
            'countGateways' => count($currentGateways)
        ];
    }


    public function bulk()
    {
        $action = Input::get('bulk_action');
        $ids = Input::get('bulk_public_id');
        $count = $this->OrganisationGatewayService->bulk($ids, $action);

        Session::flash('message', trans('texts.archived_account_gateway'));

        return Redirect::to('settings/' . ORGANISATION_PAYMENTS);
    }

    /**
     * Stores new organisation
     *
     */
    public function save($OrganisationGatewayPublicId = false)
    {
        $rules = array();
        $paymentType = Input::get('payment_type_id');
        $gatewayId = Input::get('gateway_id');

        if ($paymentType == PAYMENT_TYPE_PAYPAL) {
            $gatewayId = GATEWAY_PAYPAL_EXPRESS;
        } elseif ($paymentType == PAYMENT_TYPE_BITCOIN) {
            $gatewayId = GATEWAY_BITPAY;
        } elseif ($paymentType == PAYMENT_TYPE_DIRECT_DEBIT) {
            $gatewayId = GATEWAY_GOCARDLESS;
        } elseif ($paymentType == PAYMENT_TYPE_DWOLLA) {
            $gatewayId = GATEWAY_DWOLLA;
        }

        if (!$gatewayId) {
            Session::flash('error', trans('validation.required', ['attribute' => 'gateway']));
            return Redirect::to('gateways/create')
                ->withInput();
        }

        $gateway = Gateway::findOrFail($gatewayId);
        $fields = $gateway->getFields();
        $optional = array_merge(Gateway::$hiddenFields, Gateway::$optionalFields);

        if ($gatewayId == GATEWAY_DWOLLA) {
            $optional = array_merge($optional, ['key', 'secret']);
        } elseif ($gatewayId == GATEWAY_STRIPE) {
            if (Utils::isNinjaDev()) {
                // do nothing - we're unable to acceptance test with StripeJS
            } else {
                $rules['publishable_key'] = 'required';
            }
        }

        foreach ($fields as $field => $details) {
            if (!in_array($field, $optional)) {
                if (strtolower($gateway->name) == 'beanstream') {
                    if (in_array($field, ['merchant_id', 'passCode'])) {
                        $rules[$gateway->id.'_'.$field] = 'required';
                    }
                } else {
                    $rules[$gateway->id.'_'.$field] = 'required';
                }
            }
        }

        $creditcards = Input::get('creditCardTypes');
        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('gateways/create')
                ->withErrors($validator)
                ->withInput();
        } else {
            $organisation = Organisation::with('organisation_gateways')->findOrFail(Auth::user()->organisation_id);
            $oldConfig = null;

            if ($OrganisationGatewayPublicId) {
                $OrganisationGateway = OrganisationGateway::scope($OrganisationGatewayPublicId)->firstOrFail();
                $oldConfig = $OrganisationGateway->getConfig();
            } else {
                $OrganisationGateway = OrganisationGateway::createNew();
                $OrganisationGateway->gateway_id = $gatewayId;
            }

            $config = new stdClass();
            foreach ($fields as $field => $details) {
                $value = trim(Input::get($gateway->id.'_'.$field));
                // if the new value is masked use the original value
                if ($oldConfig && $value && $value === str_repeat('*', strlen($value))) {
                    $value = $oldConfig->$field;
                }
                if (!$value && ($field == 'testMode' || $field == 'developerMode')) {
                    // do nothing
                } else {
                    $config->$field = $value;
                }
            }

            $publishableKey = Input::get('publishable_key');
            if ($publishableKey = str_replace('*', '', $publishableKey)) {
                $config->publishableKey = $publishableKey;
            } elseif ($oldConfig && property_exists($oldConfig, 'publishableKey')) {
                $config->publishableKey = $oldConfig->publishableKey;
            }

            $cardCount = 0;
            if ($creditcards) {
                foreach ($creditcards as $card => $value) {
                    $cardCount += intval($value);
                }
            }

            $OrganisationGateway->accepted_credit_cards = $cardCount;
            $OrganisationGateway->show_address = Input::get('show_address') ? true : false;
            $OrganisationGateway->update_address = Input::get('update_address') ? true : false;
            $OrganisationGateway->setConfig($config);

            if ($OrganisationGatewayPublicId) {
                $OrganisationGateway->save();
            } else {
                $organisation->organisation_gateways()->save($OrganisationGateway);
            }

            if (Input::get('token_billing_type_id')) {
                $organisation->token_billing_type_id = Input::get('token_billing_type_id');
                $organisation->save();
            }

            if ($OrganisationGatewayPublicId) {
                $message = trans('texts.updated_gateway');
            } else {
                $message = trans('texts.created_gateway');
            }

            Session::flash('message', $message);

            return Redirect::to("gateways/{$OrganisationGateway->public_id}/edit");
        }
    }

}
