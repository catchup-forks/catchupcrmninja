<?php namespace App\Services;

use Utils;
use Auth;
use URL;
use DateTime;
use Event;
use Omnipay;
use Session;
use CreditCard;
use App\Models\Payment;
use App\Models\Organisation;
use App\Models\Country;
use App\Models\Relation;
use App\Models\Invoice;
use App\Models\OrganisationGatewayToken;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\OrganisationRepository;
use App\Services\BaseService;
use App\Events\PaymentWasCreated;

class PaymentService extends BaseService
{
    public $lastError;
    protected $datatableService;

    public function __construct(PaymentRepository $paymentRepo, OrganisationRepository $organisationRepo, DatatableService $datatableService)
    {
        $this->datatableService = $datatableService;
        $this->paymentRepo = $paymentRepo;
        $this->organisationRepo = $organisationRepo;
    }

    protected function getRepo()
    {
        return $this->paymentRepo;
    }

    public function createGateway($OrganisationGateway)
    {
        $gateway = Omnipay::create($OrganisationGateway->gateway->provider);
        $config = $OrganisationGateway->getConfig();

        foreach ($config as $key => $val) {
            if (!$val) {
                continue;
            }

            $function = "set".ucfirst($key);
            if (method_exists($gateway, $function)) {
                $gateway->$function($val);
            }
        }

        if ($OrganisationGateway->isGateway(GATEWAY_DWOLLA)) {
            if ($gateway->getSandbox() && isset($_ENV['DWOLLA_SANDBOX_KEY']) && isset($_ENV['DWOLLA_SANSBOX_SECRET'])) {
                $gateway->setKey($_ENV['DWOLLA_SANDBOX_KEY']);
                $gateway->setSecret($_ENV['DWOLLA_SANSBOX_SECRET']);
            } elseif (isset($_ENV['DWOLLA_KEY']) && isset($_ENV['DWOLLA_SECRET'])) {
                $gateway->setKey($_ENV['DWOLLA_KEY']);
                $gateway->setSecret($_ENV['DWOLLA_SECRET']);
            }
        }

        return $gateway;
    }

    public function getPaymentDetails($invitation, $OrganisationGateway, $input = null)
    {
        $invoice = $invitation->invoice;
        $organisation = $invoice->organisation;
        $key = $invoice->organisation_id.'-'.$invoice->invoice_number;
        $currencyCode = $invoice->relation->currency ? $invoice->relation->currency->code : ($invoice->organisation->currency ? $invoice->organisation->currency->code : 'USD');

        if ($input) {
            $data = self::convertInputForOmnipay($input);
            Session::put($key, $data);
        } elseif (Session::get($key)) {
            $data = Session::get($key);
        } else {
            $data = $this->createDataForRelation($invitation);
        }

        $card = new CreditCard($data);
        $data = [
            'amount' => $invoice->getRequestedAmount(),
            'card' => $card,
            'currency' => $currencyCode,
            'returnUrl' => URL::to('complete'),
            'cancelUrl' => $invitation->getLink(),
            'description' => trans('texts.' . $invoice->getEntityType()) . " {$invoice->invoice_number}",
            'transactionId' => $invoice->invoice_number,
            'transactionType' => 'Purchase',
        ];

        if ($OrganisationGateway->isGateway(GATEWAY_PAYPAL_EXPRESS) || $OrganisationGateway->isGateway(GATEWAY_PAYPAL_PRO)) {
            $data['ButtonSource'] = 'InvoiceNinja_SP';
        };

        return $data;
    }

    public function convertInputForOmnipay($input)
    {
        $data = [
            'firstName' => $input['first_name'],
            'lastName' => $input['last_name'],
            'number' => isset($input['card_number']) ? $input['card_number'] : null,
            'expiryMonth' => isset($input['expiration_month']) ? $input['expiration_month'] : null,
            'expiryYear' => isset($input['expiration_year']) ? $input['expiration_year'] : null,
            'cvv' => isset($input['cvv']) ? $input['cvv'] : '',
        ];

        if (isset($input['country_id'])) {
            $country = Country::find($input['country_id']);

            $data = array_merge($data, [
                'billingAddress1' => $input['address1'],
                'billingAddress2' => $input['housenumber'],
                'billingCity' => $input['city'],
                'billingState' => $input['state'],
                'billingPostcode' => $input['postal_code'],
                'billingCountry' => $country->iso_3166_2,
                'shippingAddress1' => $input['address1'],
                'shippingAddress2' => $input['housenumber'],
                'shippingCity' => $input['city'],
                'shippingState' => $input['state'],
                'shippingPostcode' => $input['postal_code'],
                'shippingCountry' => $country->iso_3166_2
            ]);
        }

        return $data;
    }

    public function createDataForRelation($invitation)
    {
        $invoice = $invitation->invoice;
        $relation = $invoice->relation;
        $contact = $invitation->contact ?: $relation->contacts()->first();

        return [
            'email' => $contact->email,
            'company' => $relation->getDisplayName(),
            'firstName' => $contact->first_name,
            'lastName' => $contact->last_name,
            'billingAddress1' => $relation->address1,
            'billingAddress2' => $relation->housenumber,
            'billingCity' => $relation->city,
            'billingPostcode' => $relation->postal_code,
            'billingState' => $relation->state,
            'billingCountry' => $relation->country ? $relation->country->iso_3166_2 : '',
            'billingPhone' => $contact->phone,
            'shippingAddress1' => $relation->address1,
            'shippingAddress2' => $relation->housenumber,
            'shippingCity' => $relation->city,
            'shippingPostcode' => $relation->postal_code,
            'shippingState' => $relation->state,
            'shippingCountry' => $relation->country ? $relation->country->iso_3166_2 : '',
            'shippingPhone' => $contact->phone,
        ];
    }

    public function createToken($gateway, $details, $OrganisationGateway, $relation, $contactId)
    {
        $tokenResponse = $gateway->createCard($details)->send();
        $cardReference = $tokenResponse->getCustomerReference();

        if ($cardReference) {
            $token = OrganisationGatewayToken::where('relation_id', '=', $relation->id)
            ->where('account_gateway_id', '=', $OrganisationGateway->id)->first();

            if (!$token) {
                $token = new OrganisationGatewayToken();
                $token->organisation_id = $relation->organisation->id;
                $token->contact_id = $contactId;
                $token->account_gateway_id = $OrganisationGateway->id;
                $token->relation_id = $relation->id;
            }

            $token->token = $cardReference;
            $token->save();
        } else {
            $this->lastError = $tokenResponse->getMessage();
        }

        return $cardReference;
    }

    public function getCheckoutComToken($invitation)
    {
        $token = false;
        $invoice = $invitation->invoice;
        $relation = $invoice->relation;
        $organisation = $invoice->organisation;

        $OrganisationGateway = $organisation->getGatewayConfig(GATEWAY_CHECKOUT_COM);
        $gateway = $this->createGateway($OrganisationGateway);

        $response = $gateway->purchase([
            'amount' => $invoice->getRequestedAmount(),
            'currency' => $relation->currency ? $relation->currency->code : ($organisation->currency ? $organisation->currency->code : 'USD')
        ])->send();

        if ($response->isRedirect()) {
            $token = $response->getTransactionReference();
        }

        Session::set($invitation->id . 'payment_type', PAYMENT_TYPE_CREDIT_CARD);

        return $token;
    }

    public function createPayment($invitation, $OrganisationGateway, $ref, $payerId = null)
    {
        $invoice = $invitation->invoice;

        // enable pro plan for hosted users
        if ($invoice->organisation->organisation_key == NINJA_ORGANISATION_KEY && $invoice->amount == PRO_PLAN_PRICE) {
            $organisation = Organisation::with('users')->find($invoice->relation->public_id);
            $organisation->pro_plan_paid = $organisation->getRenewalDate();
            $organisation->save();

            // sync pro organisations
            $user = $organisation->users()->first();
            $this->organisationRepo->syncOrganisations($user->id, $organisation->pro_plan_paid);
        }

        $payment = Payment::createNew($invitation);
        $payment->invitation_id = $invitation->id;
        $payment->account_gateway_id = $OrganisationGateway->id;
        $payment->invoice_id = $invoice->id;
        $payment->amount = $invoice->getRequestedAmount();
        $payment->relation_id = $invoice->relation_id;
        $payment->contact_id = $invitation->contact_id;
        $payment->transaction_reference = $ref;
        $payment->payment_date = date_create()->format('Y-m-d');

        if ($payerId) {
            $payment->payer_id = $payerId;
        }

        $payment->save();

        return $payment;
    }

    public function completePurchase($gateway, $OrganisationGateway, $details, $token)
    {
        if ($OrganisationGateway->isGateway(GATEWAY_MOLLIE)) {
            $details['transactionReference'] = $token;
            $response = $gateway->fetchTransaction($details)->send();
            return $gateway->fetchTransaction($details)->send();
        } else {

            return $gateway->completePurchase($details)->send();
        }
    }

    public function autoBillInvoice($invoice)
    {
        $relation = $invoice->relation;
        $organisation = $invoice->organisation;
        $invitation = $invoice->invitations->first();
        $OrganisationGateway = $organisation->getGatewayConfig(GATEWAY_STRIPE);
        $token = $relation->getGatewayToken();

        if (!$invitation || !$OrganisationGateway || !$token) {
            return false;
        }

        // setup the gateway/payment info
        $gateway = $this->createGateway($OrganisationGateway);
        $details = $this->getPaymentDetails($invitation, $OrganisationGateway);
        $details['customerReference'] = $token;

        // submit purchase/get response
        $response = $gateway->purchase($details)->send();

        if ($response->isSuccessful()) {
            $ref = $response->getTransactionReference();
            return $this->createPayment($invitation, $OrganisationGateway, $ref);
        } else {
            return false;
        }
    }

    public function getDatatable($relationPublicId, $search)
    {
        $query = $this->paymentRepo->find($relationPublicId, $search);

        if(!Utils::hasPermission('view_all')){
            $query->where('payments.user_id', '=', Auth::user()->id);
        }

        return $this->createDatatable(ENTITY_PAYMENT, $query, !$relationPublicId);
    }

    protected function getDatatableColumns($entityType, $hideRelation)
    {
        return [
            [
                'invoice_number',
                function ($model) {
                    if(!Invoice::canEditItemByOwner($model->invoice_user_id)){
                        return $model->invoice_number;
                    }
                    
                    return link_to("invoices/{$model->invoice_public_id}/edit", $model->invoice_number, ['class' => Utils::getEntityRowClass($model)])->toHtml();
                }
            ],
            [
                'relation_name',
                function ($model) {
                    if(!Relation::canViewItemByOwner($model->relation_user_id)){
                        return Utils::getRelationDisplayName($model);
                    }
                    
                    return $model->relation_public_id ? link_to("relations/{$model->relation_public_id}", Utils::getRelationDisplayName($model))->toHtml() : '';
                },
                ! $hideRelation
            ],
            [
                'transaction_reference',
                function ($model) {
                    return $model->transaction_reference ? $model->transaction_reference : '<i>Manual entry</i>';
                }
            ],
            [
                'payment_type',
                function ($model) {
                    return $model->payment_type ? $model->payment_type : ($model->account_gateway_id ? $model->gateway_name : '');
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                }
            ],
            [
                'payment_date',
                function ($model) {
                    return Utils::dateToString($model->payment_date);
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.edit_payment'),
                function ($model) {
                    return URL::to("payments/{$model->public_id}/edit");
                },
                function ($model) {
                    return Payment::canEditItem($model);
                }
            ]
        ];
    }


}
