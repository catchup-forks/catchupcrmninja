<?php namespace App\Http\Controllers;

use Datatable;
use Input;
use Redirect;
use Request;
use Session;
use Utils;
use View;
use Validator;
use Omnipay;
use CreditCard;
use URL;
use Cache;
use App\Models\Invoice;
use App\Models\Invitation;
use App\Models\Relation;
use App\Models\PaymentType;
use App\Models\License;
use App\Models\Payment;
use App\Models\Affiliate;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\OrganisationRepository;
use App\Ninja\Mailers\ContactMailer;
use App\Services\PaymentService;

use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;

class PaymentController extends BaseController
{
    protected $model = 'App\Models\Payment';
    
    public function __construct(PaymentRepository $paymentRepo, InvoiceRepository $invoiceRepo, OrganisationRepository $organisationRepo, ContactMailer $contactMailer, PaymentService $paymentService)
    {
        // parent::__construct();

        $this->paymentRepo = $paymentRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->organisationRepo = $organisationRepo;
        $this->contactMailer = $contactMailer;
        $this->paymentService = $paymentService;
    }

    public function index()
    {
        return View::make('list', array(
            'entityType' => ENTITY_PAYMENT,
            'title' => trans('texts.payments'),
            'sortCol' => '6',
            'columns' => Utils::trans([
              'checkbox',
              'invoice',
              'relation',
              'transaction_reference',
              'method',
              'payment_amount',
              'payment_date',
              ''
            ]),
        ));
    }

    public function getDatatable($relationPublicId = null)
    {
        return $this->paymentService->getDatatable($relationPublicId, Input::get('sSearch'));
    }

    public function create($relationPublicId = 0, $invoicePublicId = 0)
    {
        if(!$this->checkCreatePermission($response)){
            return $response;
        }
        
        $invoices = Invoice::scope()
                    ->where('is_recurring', '=', false)
                    ->where('is_quote', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->with('relation', 'invoice_status')
                    ->orderBy('invoice_number')->get();

        $data = array(
            'relationPublicId' => Input::old('relation') ? Input::old('relation') : $relationPublicId,
            'invoicePublicId' => Input::old('invoice') ? Input::old('invoice') : $invoicePublicId,
            'invoice' => null,
            'invoices' => $invoices,
            'payment' => null,
            'method' => 'POST',
            'url' => "payments",
            'title' => trans('texts.new_payment'),
            'paymentTypes' => Cache::get('paymentTypes'),
            'paymentTypeId' => Input::get('paymentTypeId'),
            'relations' => Relation::scope()->with('contacts')->orderBy('name')->get(), );

        return View::make('payments.edit', $data);
    }

    public function edit($publicId)
    {
        $payment = Payment::scope($publicId)->firstOrFail();
        
        if(!$this->checkEditPermission($payment, $response)){
            return $response;
        }
        
        $payment->payment_date = Utils::fromSqlDate($payment->payment_date);

        $data = array(
            'relation' => null,
            'invoice' => null,
            'invoices' => Invoice::scope()->where('is_recurring', '=', false)->where('is_quote', '=', false)
                            ->with('relation', 'invoice_status')->orderBy('invoice_number')->get(),
            'payment' => $payment,
            'method' => 'PUT',
            'url' => 'payments/'.$publicId,
            'title' => trans('texts.edit_payment'),
            'paymentTypes' => Cache::get('paymentTypes'),
            'relations' => Relation::scope()->with('contacts')->orderBy('name')->get(), );

        return View::make('payments.edit', $data);
    }

    private function getLicensePaymentDetails($input, $affiliate)
    {
        $data = $this->paymentService->convertInputForOmnipay($input);
        $card = new CreditCard($data);

        return [
            'amount' => $affiliate->price,
            'card' => $card,
            'currency' => 'USD',
            'returnUrl' => URL::to('license_complete'),
            'cancelUrl' => URL::to('/')
        ];
    }

    public function show_payment($invitationKey, $paymentType = false)
    {

        $invitation = Invitation::with('invoice.invoice_items', 'invoice.relation.currency', 'invoice.relation.organisation.organisation_gateways.gateway')->where('invitation_key', '=', $invitationKey)->firstOrFail();
        $invoice = $invitation->invoice;
        $relation = $invoice->relation;
        $organisation = $relation->organisation;
        $useToken = false;

        if ($paymentType) {
            $paymentType = 'PAYMENT_TYPE_' . strtoupper($paymentType);
        } else {
            $paymentType = Session::get($invitation->id . 'payment_type') ?:
                                $organisation->organisation_gateways[0]->getPaymentType();
        }

        if ($paymentType == PAYMENT_TYPE_TOKEN) {
            $useToken = true;
            $paymentType = PAYMENT_TYPE_CREDIT_CARD;
        }
        Session::put($invitation->id . 'payment_type', $paymentType);

        $OrganisationGateway = $invoice->relation->organisation->getGatewayByType($paymentType);
        $gateway = $OrganisationGateway->gateway;

        $acceptedCreditCardTypes = $OrganisationGateway->getCreditcardTypes();


        // Handle offsite payments
        if ($useToken || $paymentType != PAYMENT_TYPE_CREDIT_CARD
            || $gateway->id == GATEWAY_EWAY
            || $gateway->id == GATEWAY_TWO_CHECKOUT
            || $gateway->id == GATEWAY_PAYFAST
            || $gateway->id == GATEWAY_MOLLIE) {
            if (Session::has('error')) {
                Session::reflash();
                return Redirect::to('view/'.$invitationKey);
            } else {
                return self::do_payment($invitationKey, false, $useToken);
            }
        }

        $data = [
            'showBreadcrumbs' => false,
            'url' => 'payment/'.$invitationKey,
            'amount' => $invoice->getRequestedAmount(),
            'invoiceNumber' => $invoice->invoice_number,
            'relation' => $relation,
            'contact' => $invitation->contact,
            'gateway' => $gateway,
            'OrganisationGateway' => $OrganisationGateway,
            'acceptedCreditCardTypes' => $acceptedCreditCardTypes,
            'countries' => Cache::get('countries'),
            'currencyId' => $relation->getCurrencyId(),
            'currencyCode' => $relation->currency ? $relation->currency->code : ($organisation->currency ? $organisation->currency->code : 'USD'),
            'organisation' => $relation->organisation,
            'hideLogo' => $organisation->isWhiteLabel(),
            'hideHeader' => $organisation->isNinjaOrganisation(),
            'relationViewCSS' => $organisation->relationViewCSS(),
            'relationFontUrl' => $organisation->getFontsUrl(),
            'showAddress' => $OrganisationGateway->show_address,
        ];

        return View::make('payments.payment', $data);
    }

    public function show_license_payment()
    {
        if (Input::has('return_url')) {
            Session::set('return_url', Input::get('return_url'));
        }

        if (Input::has('affiliate_key')) {
            if ($affiliate = Affiliate::where('affiliate_key', '=', Input::get('affiliate_key'))->first()) {
                Session::set('affiliate_id', $affiliate->id);
            }
        }

        if (Input::has('product_id')) {
            Session::set('product_id', Input::get('product_id'));
        } else if (!Session::has('product_id')) {
            Session::set('product_id', PRODUCT_ONE_CLICK_INSTALL);
        }

        if (!Session::get('affiliate_id')) {
            return Utils::fatalError();
        }

        if (Utils::isNinjaDev() && Input::has('test_mode')) {
            Session::set('test_mode', Input::get('test_mode'));
        }

        $organisation = $this->organisationRepo->getNinjaAccount();
        $organisation->load('organisation_gateways.gateway');
        $OrganisationGateway = $organisation->getGatewayByType(PAYMENT_TYPE_CREDIT_CARD);
        $gateway = $OrganisationGateway->gateway;
        $acceptedCreditCardTypes = $OrganisationGateway->getCreditcardTypes();

        $affiliate = Affiliate::find(Session::get('affiliate_id'));

        $data = [
            'showBreadcrumbs' => false,
            'hideHeader' => true,
            'url' => 'license',
            'amount' => $affiliate->price,
            'relation' => false,
            'contact' => false,
            'gateway' => $gateway,
            'organisation' => $organisation,
            'OrganisationGateway' => $OrganisationGateway,
            'acceptedCreditCardTypes' => $acceptedCreditCardTypes,
            'countries' => Cache::get('countries'),
            'currencyId' => 1,
            'currencyCode' => 'USD',
            'paymentTitle' => $affiliate->payment_title,
            'paymentSubtitle' => $affiliate->payment_subtitle,
            'showAddress' => true,
        ];

        return View::make('payments.payment', $data);
    }

    public function do_license_payment()
    {
        $testMode = Session::get('test_mode') === 'true';

        $rules = array(
            'first_name' => 'required',
            'last_name' => 'required',
            'card_number' => 'required',
            'expiration_month' => 'required',
            'expiration_year' => 'required',
            'cvv' => 'required',
            'address1' => 'required',
            'city' => 'required',
            'state' => 'required',
            'postal_code' => 'required',
            'country_id' => 'required',
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('license')
                ->withErrors($validator)
                ->withInput();
        }

        $organisation = $this->organisationRepo->getNinjaAccount();
        $organisation->load('organisation_gateways.gateway');
        $OrganisationGateway = $organisation->getGatewayByType(PAYMENT_TYPE_CREDIT_CARD);

        try {
            $affiliate = Affiliate::find(Session::get('affiliate_id'));

            if ($testMode) {
                $ref = 'TEST_MODE';
            } else {
                $gateway = $this->paymentService->createGateway($OrganisationGateway);
                $details = self::getLicensePaymentDetails(Input::all(), $affiliate);
                $response = $gateway->purchase($details)->send();
                $ref = $response->getTransactionReference();

                if (!$response->isSuccessful() || !$ref) {
                    $this->error('License', $response->getMessage(), $OrganisationGateway);
                    return Redirect::to('license')->withInput();
                }
            }

            $licenseKey = Utils::generateLicense();

            $license = new License();
            $license->first_name = Input::get('first_name');
            $license->last_name = Input::get('last_name');
            $license->email = Input::get('email');
            $license->transaction_reference = $ref;
            $license->license_key = $licenseKey;
            $license->affiliate_id = Session::get('affiliate_id');
            $license->product_id = Session::get('product_id');
            $license->save();

            $data = [
                'message' => $affiliate->payment_subtitle,
                'license' => $licenseKey,
                'hideHeader' => true,
                'productId' => $license->product_id,
                'price' => $affiliate->price,
            ];

            $name = "{$license->first_name} {$license->last_name}";
            $this->contactMailer->sendLicensePaymentConfirmation($name, $license->email, $affiliate->price, $license->license_key, $license->product_id);

            if (Session::has('return_url')) {
                $data['redirectTo'] = Session::get('return_url')."?license_key={$license->license_key}&product_id=".Session::get('product_id');
                $data['message'] = "Redirecting to " . Session::get('return_url');
            }

            return View::make('public.license', $data);
        } catch (\Exception $e) {
            $this->error('License-Uncaught', false, $OrganisationGateway, $e);
            return Redirect::to('license')->withInput();
        }
    }

    public function claim_license()
    {
        $licenseKey = Input::get('license_key');
        $productId = Input::get('product_id', PRODUCT_ONE_CLICK_INSTALL);

        $license = License::where('license_key', '=', $licenseKey)
                    ->where('is_claimed', '<', 5)
                    ->where('product_id', '=', $productId)
                    ->first();

        if ($license) {
            if ($license->transaction_reference != 'TEST_MODE') {
                $license->is_claimed = $license->is_claimed + 1;
                $license->save();
            }

            return $productId == PRODUCT_INVOICE_DESIGNS ? file_get_contents(storage_path() . '/invoice_designs.txt') : 'valid';
        } else {
            return 'invalid';
        }
    }

    public function do_payment($invitationKey, $onSite = true, $useToken = false)
    {
        $invitation = Invitation::with('invoice.invoice_items', 'invoice.relation.currency', 'invoice.relation.organisation.currency', 'invoice.relation.organisation.organisation_gateways.gateway')->where('invitation_key', '=', $invitationKey)->firstOrFail();
        $invoice = $invitation->invoice;
        $relation = $invoice->relation;
        $organisation = $relation->organisation;
        $OrganisationGateway = $organisation->getGatewayByType(Session::get($invitation->id . 'payment_type'));


        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
        ];

        if ( ! Input::get('stripeToken')) {
            $rules = array_merge(
                $rules,
                [
                    'card_number' => 'required',
                    'expiration_month' => 'required',
                    'expiration_year' => 'required',
                    'cvv' => 'required',
                ]
            );
        }

        if ($OrganisationGateway->show_address) {
            $rules = array_merge($rules, [
                'address1' => 'required',
                'city' => 'required',
                'state' => 'required',
                'postal_code' => 'required',
                'country_id' => 'required',
            ]);
        }

        if ($onSite) {
            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to('payment/'.$invitationKey)
                    ->withErrors($validator)
                    ->withInput(Request::except('cvv'));
            }

            if ($OrganisationGateway->update_address) {
                $relation->address1 = trim(Input::get('address1'));
                $relation->housenumber = trim(Input::get('housenumber'));
                $relation->city = trim(Input::get('city'));
                $relation->state = trim(Input::get('state'));
                $relation->postal_code = trim(Input::get('postal_code'));
                $relation->country_id = Input::get('country_id');
                $relation->save();
            }
        }

        try {
            // For offsite payments send the relation's details on file
            // If we're using a token then we don't need to send any other data
            if (!$onSite || $useToken) {
                $data = false;
            } else {
                $data = Input::all();
            }

            $gateway = $this->paymentService->createGateway($OrganisationGateway);
            $details = $this->paymentService->getPaymentDetails($invitation, $OrganisationGateway, $data);

            // check if we're creating/using a billing token
            if ($OrganisationGateway->gateway_id == GATEWAY_STRIPE) {
                if ($token = Input::get('stripeToken')) {
                    $details['token'] = $token;
                    unset($details['card']);
                }

                if ($useToken) {
                    $details['customerReference'] = $relation->getGatewayToken();
                } elseif ($organisation->token_billing_type_id == TOKEN_BILLING_ALWAYS || Input::get('token_billing')) {
                    $token = $this->paymentService->createToken($gateway, $details, $OrganisationGateway, $relation, $invitation->contact_id);
                    if ($token) {
                        $details['customerReference'] = $token;
                    } else {
                        $this->error('Token-No-Ref', $this->paymentService->lastError, $OrganisationGateway);
                        return Redirect::to('payment/'.$invitationKey)->withInput(Request::except('cvv'));
                    }
                }
            }

            $response = $gateway->purchase($details)->send();


            if ($OrganisationGateway->gateway_id == GATEWAY_EWAY) {
                $ref = $response->getData()['AccessCode'];
            } elseif ($OrganisationGateway->gateway_id == GATEWAY_TWO_CHECKOUT) {
                $ref = $response->getData()['cart_order_id'];
            } elseif ($OrganisationGateway->gateway_id == GATEWAY_PAYFAST) {
                $ref = $response->getData()['m_payment_id'];
            } elseif ($OrganisationGateway->gateway_id == GATEWAY_GOCARDLESS) {
                $ref = $response->getData()['signature'];
            } else {
                $ref = $response->getTransactionReference();
            }

            if (!$ref) {
                $this->error('No-Ref', $response->getMessage(), $OrganisationGateway);

                if ($onSite) {
                    return Redirect::to('payment/'.$invitationKey)
                            ->withInput(Request::except('cvv'));
                } else {
                    return Redirect::to('view/'.$invitationKey);
                }
            }

            if ($response->isSuccessful()) {
                $payment = $this->paymentService->createPayment($invitation, $OrganisationGateway, $ref);
                Session::flash('message', trans('texts.applied_payment'));

                if ($organisation->organisation_key == NINJA_ORGANISATION_KEY) {
                    Session::flash('trackEventCategory', '/organisation');
                    Session::flash('trackEventAction', '/buy_pro_plan');
                }

                return Redirect::to('view/'.$payment->invitation->invitation_key);
            } elseif ($response->isRedirect()) {

                $invitation->transaction_reference = $ref;
                $invitation->save();
                Session::put('transaction_reference', $ref);
                Session::save();
                $response->redirect();
            } else {
                $this->error('Unknown', $response->getMessage(), $OrganisationGateway);
                if ($onSite) {
                    return Redirect::to('payment/'.$invitationKey)->withInput(Request::except('cvv'));
                } else {
                    return Redirect::to('view/'.$invitationKey);
                }
            }
        } catch (\Exception $e) {
            $this->error('Uncaught', false, $OrganisationGateway, $e);
            if ($onSite) {
                return Redirect::to('payment/'.$invitationKey)->withInput(Request::except('cvv'));
            } else {
                return Redirect::to('view/'.$invitationKey);
            }
        }
    }

    public function offsite_payment()
    {
        $payerId = Request::query('PayerID');
        $token = Request::query('token');

        if (!$token) {
            $token = Session::pull('transaction_reference');
        }
        if (!$token) {
            return redirect(NINJA_WEB_URL);
        }

        $invitation = Invitation::with('invoice.relation.currency', 'invoice.relation.organisation.organisation_gateways.gateway')->where('transaction_reference', '=', $token)->firstOrFail();
        $invoice = $invitation->invoice;
        $relation = $invoice->relation;
        $organisation = $relation->organisation;

        if ($payerId) {
            $paymentType = PAYMENT_TYPE_PAYPAL;
        } else {
            $paymentType = Session::get($invitation->id . 'payment_type');
        }
        if (!$paymentType) {
            $this->error('No-Payment-Type', false, false);
            return Redirect::to($invitation->getLink());
        }
        $OrganisationGateway = $organisation->getGatewayByType($paymentType);
        $gateway = $this->paymentService->createGateway($OrganisationGateway);

        // Check for Dwolla payment error
        if ($OrganisationGateway->isGateway(GATEWAY_DWOLLA) && Input::get('error')) {
            $this->error('Dwolla', Input::get('error_description'), $OrganisationGateway);
            return Redirect::to($invitation->getLink());
        }

        // PayFast transaction referencce
        if ($OrganisationGateway->isGateway(GATEWAY_PAYFAST) && Request::has('pt')) {
            $token = Request::query('pt');
        }

        try {
            if (method_exists($gateway, 'completePurchase') 
                && !$OrganisationGateway->isGateway(GATEWAY_TWO_CHECKOUT)
                && !$OrganisationGateway->isGateway(GATEWAY_CHECKOUT_COM)) {
                $details = $this->paymentService->getPaymentDetails($invitation, $OrganisationGateway);

                $response = $this->paymentService->completePurchase($gateway, $OrganisationGateway, $details, $token);

                $ref = $response->getTransactionReference() ?: $token;

                if ($response->isCancelled()) {
                    // do nothing
                } elseif ($response->isSuccessful()) {
                    $payment = $this->paymentService->createPayment($invitation, $OrganisationGateway, $ref, $payerId);
                    Session::flash('message', trans('texts.applied_payment'));
                } else {
                    $this->error('offsite', $response->getMessage(), $OrganisationGateway);
                }
                return Redirect::to($invitation->getLink());
            } else {
                $payment = $this->paymentService->createPayment($invitation, $OrganisationGateway, $token, $payerId);
                Session::flash('message', trans('texts.applied_payment'));

                return Redirect::to($invitation->getLink());
            }
        } catch (\Exception $e) {

            $this->error('Offsite-uncaught', false, $OrganisationGateway, $e);
            return Redirect::to($invitation->getLink());
        }
    }

    public function store(CreatePaymentRequest $request)
    {
        $input = $request->input();
        
        if(!$this->checkUpdatePermission($input, $response)){
            return $response;
        }
        
        $input['invoice_id'] = Invoice::getPrivateId($input['invoice']);
        $input['relation_id'] = Relation::getPrivateId($input['relation']);
        $payment = $this->paymentRepo->save($input);

        if (Input::get('email_receipt')) {
            $this->contactMailer->sendPaymentConfirmation($payment);
            Session::flash('message', trans('texts.created_payment_emailed_relation'));
        } else {
            Session::flash('message', trans('texts.created_payment'));
        }

        return redirect()->to($payment->relation->getRoute());
    }

    public function update(UpdatePaymentRequest $request)
    {
        $input = $request->input();
                
        if(!$this->checkUpdatePermission($input, $response)){
            return $response;
        }
        
        $payment = $this->paymentRepo->save($input);

        Session::flash('message', trans('texts.updated_payment'));

        return redirect()->to($payment->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->paymentService->bulk($ids, $action);

        if ($count > 0) {
            $message = Utils::pluralize($action.'d_payment', $count);
            Session::flash('message', $message);
        }

        return Redirect::to('payments');
    }

    private function error($type, $error, $OrganisationGateway = false, $exception = false)
    {
        $message = '';
        if ($OrganisationGateway && $OrganisationGateway->gateway) {
            $message = $OrganisationGateway->gateway->name . ': ';
        }
        $message .= $error ?: trans('texts.payment_error');

        Session::flash('error', $message);
        Utils::logError("Payment Error [{$type}]: " . ($exception ? Utils::getErrorString($exception) : $message));
    }
}
