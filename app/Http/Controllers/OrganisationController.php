<?php namespace App\Http\Controllers;

use Auth;
use File;
use Image;
use Input;
use Redirect;
use Session;
use Utils;
use Validator;
use View;
use stdClass;
use Cache;
use Response;
use Request;
use App\Models\Affiliate;
use App\Models\License;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Organisation;
use App\Models\Gateway;
use App\Models\InvoiceDesign;
use App\Models\TaxRate;
use App\Models\PaymentTerm;
use App\Ninja\Repositories\OrganisationRepository;
use App\Ninja\Repositories\ReferralRepository;
use App\Ninja\Mailers\UserMailer;
use App\Ninja\Mailers\ContactMailer;
use App\Events\UserSignedUp;
use App\Events\UserSettingsChanged;
use App\Services\AuthService;

use App\Http\Requests\UpdateOrganisationRequest;

class OrganisationController extends BaseController
{
    protected $organisationRepo;
    protected $userMailer;
    protected $contactMailer;
    protected $referralRepository;

    public function __construct(OrganisationRepository $organisationRepo, UserMailer $userMailer, ContactMailer $contactMailer, ReferralRepository $referralRepository)
    {
        //parent::__construct();

        $this->organisationRepo = $organisationRepo;
        $this->userMailer = $userMailer;
        $this->contactMailer = $contactMailer;
        $this->referralRepository = $referralRepository;
    }

    public function demo()
    {
        $demoOrganisationId = Utils::getDemoOrganisationId();

        if (!$demoOrganisationId) {
            return Redirect::to('/');
        }

        $organisation = Organisation::find($demoOrganisationId);
        $user = $organisation->users()->first();

        Auth::login($user, true);

        return Redirect::to('invoices/create');
    }

    public function getStarted()
    {
        $user = false;
        $guestKey = Input::get('guest_key'); // local storage key to login until registered
        $prevUserId = Session::pull(PREV_USER_ID); // last user id used to link to new organisation

        if (Auth::check()) {
            return Redirect::to('invoices/create');
        }

        if (!Utils::isNinja() && (Organisation::count() > 0 && !$prevUserId)) {
            return Redirect::to('/login');
        }

        if ($guestKey && !$prevUserId) {
            $user = User::where('password', '=', $guestKey)->first();

            if ($user && $user->registered) {
                return Redirect::to('/');
            }
        }

        if (!$user) {
            $organisation = $this->organisationRepo->create();
            $user = $organisation->users()->first();

            Session::forget(RECENTLY_VIEWED);

            if ($prevUserId) {
                $users = $this->organisationRepo->associateOrganisations($user->id, $prevUserId);
                Session::put(SESSION_USER_ORGANISATIONS, $users);
            }
        }

        Auth::login($user, true);
        event(new UserSignedUp());

        $redirectTo = Input::get('redirect_to') ?: 'invoices/create';

        return Redirect::to($redirectTo)->with('sign_up', Input::get('sign_up'));
    }

    public function enableProPlan()
    {
        $invitation = $this->organisationRepo->enableProPlan();

        return $invitation->invitation_key;
    }

    public function setTrashVisible($entityType, $visible)
    {
        Session::put("show_trash:{$entityType}", $visible == 'true');

        return RESULT_SUCCESS;
    }

    public function getSearchData()
    {
        $organisation = Auth::user()->organisation;
        $data = $this->organisationRepo->getSearchData($organisation);

        return Response::json($data);
    }

    public function showSection($section = false)
    {
        if (!$section) {
            return Redirect::to('/settings/'.ORGANISATION_COMPANY_DETAILS, 301);
        }

        if ($section == ORGANISATION_COMPANY_DETAILS) {
            return self::showCompanyDetails();
        } elseif ($section == ORGANISATION_LOCALIZATION) {
            return self::showLocalization();
        } elseif ($section == ORGANISATION_PAYMENTS) {
            return self::showOnlinePayments();
        } elseif ($section == ORGANISATION_BANKS) {
            return self::showBankAccounts();
        } elseif ($section == ORGANISATION_INVOICE_SETTINGS) {
            return self::showInvoiceSettings();
        } elseif ($section == ORGANISATION_IMPORT_EXPORT) {
            return View::make('organisations.import_export', ['title' => trans('texts.import_export')]);
        } elseif ($section == ORGANISATION_INVOICE_DESIGN || $section == ORGANISATION_CUSTOMIZE_DESIGN) {
            return self::showInvoiceDesign($section);
        } elseif ($section == ORGANISATION_RELATION_PORTAL) {
            return self::showRelationPortal();
        } elseif ($section === ORGANISATION_TEMPLATES_AND_REMINDERS) {
            return self::showTemplates();
        } elseif ($section === ORGANISATION_PRODUCTS) {
            return self::showProducts();
        } elseif ($section === ORGANISATION_TAX_RATES) {
            return self::showTaxRates();
        } elseif ($section === ORGANISATION_PAYMENT_TERMS) {
            return self::showPaymentTerms();

        /*
        } elseif ($section === ORGANISATION_SYSTEM_SETTINGS) {
            return self::showSystemSettings();
            */
        } else {
            $data = [
                'organisation' => Organisation::with('users')->findOrFail(Auth::user()->organisation_id),
                'title' => trans("texts.{$section}"),
                'section' => $section,
            ];

            return View::make("organisations.{$section}", $data);
        }
    }

    /*
    private function showSystemSettings()
    {
        if (Utils::isNinjaProd()) {
            return Redirect::to('/');
        }

        $data = [
            'organisation' => Organisation::with('users')->findOrFail(Auth::user()->organisation_id),
            'title' => trans("texts.system_settings"),
            'section' => ORGANISATION_SYSTEM_SETTINGS,
        ];

        return View::make("organisations.system_settings", $data);
    }
    */

    private function showInvoiceSettings()
    {
        $organisation = Auth::user()->organisation;
        $recurringHours = [];

        for ($i = 0; $i<24; $i++) {
            if ($organisation->military_time) {
                $format = 'H:i';
            } else {
                $format = 'g:i a';
            }
            $recurringHours[$i] = date($format, strtotime("{$i}:00"));
        }

        $data = [
            'organisation' => Organisation::with('users')->findOrFail(Auth::user()->organisation_id),
            'title' => trans("texts.invoice_settings"),
            'section' => ORGANISATION_INVOICE_SETTINGS,
            'recurringHours' => $recurringHours,
        ];

        return View::make("organisations.invoice_settings", $data);
    }

    private function showCompanyDetails()
    {
        // check that logo is less than the max file size
        $organisation = Auth::user()->organisation;
        if ($organisation->isLogoTooLarge()) {
            Session::flash('warning', trans('texts.logo_too_large', ['size' => $organisation->getLogoSize().'KB']));
        }

        $data = [
            'organisation' => Organisation::with('users')->findOrFail(Auth::user()->organisation_id),
            'countries' => Cache::get('countries'),
            'sizes' => Cache::get('sizes'),
            'industries' => Cache::get('industries'),
            'title' => trans('texts.company_details'),
        ];

        return View::make('organisations.details', $data);
    }

    public function showUserDetails()
    {
        $oauthLoginUrls = [];
        foreach (AuthService::$providers as $provider) {
            $oauthLoginUrls[] = ['label' => $provider, 'url' => '/auth/'.strtolower($provider)];
        }

        $data = [
            'organisation' => Organisation::with('users')->findOrFail(Auth::user()->organisation_id),
            'title' => trans('texts.user_details'),
            'user' => Auth::user(),
            'oauthProviderName' => AuthService::getProviderName(Auth::user()->oauth_provider_id),
            'oauthLoginUrls' => $oauthLoginUrls,
            'referralCounts' => $this->referralRepository->getCounts(Auth::user()->id),
        ];

        return View::make('organisations.user_details', $data);
    }

    private function showLocalization()
    {
        $data = [
            'organisation' => Organisation::with('users')->findOrFail(Auth::user()->organisation_id),
            'timezones' => Cache::get('timezones'),
            'dateFormats' => Cache::get('dateFormats'),
            'datetimeFormats' => Cache::get('datetimeFormats'),
            'currencies' => Cache::get('currencies'),
            'languages' => Cache::get('languages'),
            'title' => trans('texts.localization'),
        ];

        return View::make('organisations.localization', $data);
    }

    private function showBankAccounts()
    {
        $organisation = Auth::user()->organisation;
        $organisation->load('bank_accounts');
        $count = count($organisation->bank_accounts);

        if ($count == 0) {
            return Redirect::to('bank_accounts/create');
        } else {
            return View::make('organisations.banks', [
                'title' => trans('texts.bank_accounts')
            ]);
        }
    }

    private function showOnlinePayments()
    {
        $organisation = Auth::user()->organisation;
        $organisation->load('organisation_gateways');
        $count = count($organisation->organisation_gateways);

        if ($OrganisationGateway = $organisation->getGatewayConfig(GATEWAY_STRIPE)) {
            if (! $OrganisationGateway->getPublishableStripeKey()) {
                Session::flash('warning', trans('texts.missing_publishable_key'));
            }
        }

        if ($count == 0) {
            return Redirect::to('gateways/create');
        } else {
            return View::make('organisations.payments', [
                'showAdd' => $count < count(Gateway::$paymentTypes),
                'title' => trans('texts.online_payments')
            ]);
        }
    }

    private function showProducts()
    {
        $columns = ['product', 'description', 'unit_cost'];
        if (Auth::user()->organisation->invoice_item_taxes) {
            $columns[] = 'tax_rate';
        }
        $columns[] = 'action';

        $data = [
            'organisation' => Auth::user()->organisation,
            'title' => trans('texts.product_library'),
            'columns' => Utils::trans($columns),
        ];

        return View::make('organisations.products', $data);
    }

    private function showTaxRates()
    {
        $data = [
            'organisation' => Auth::user()->organisation,
            'title' => trans('texts.tax_rates'),
            'taxRates' => TaxRate::scope()->get(['id', 'name', 'rate']),
        ];

        return View::make('organisations.tax_rates', $data);
    }

    private function showPaymentTerms()
    {
        $data = [
            'organisation' => Auth::user()->organisation,
            'title' => trans('texts.payment_terms'),
            'taxRates' => PaymentTerm::scope()->get(['id', 'name', 'num_days']),
        ];

        return View::make('organisations.payment_terms', $data);
    }

    private function showInvoiceDesign($section)
    {
        $organisation = Auth::user()->organisation->load('country');
        $invoice = new stdClass();
        $relation = new stdClass();
        $contact = new stdClass();
        $invoiceItem = new stdClass();

        $relation->name = 'Sample Relation';
        $relation->address1 = trans('texts.address1');
        $relation->city = trans('texts.city');
        $relation->state = trans('texts.state');
        $relation->postal_code = trans('texts.postal_code');
        $relation->work_phone = trans('texts.work_phone');
        $relation->work_email = trans('texts.work_id');
        
        $invoice->invoice_number = '0000';
        $invoice->invoice_date = Utils::fromSqlDate(date('Y-m-d'));
        $invoice->organisation = json_decode($organisation->toJson());
        $invoice->amount = $invoice->balance = 100;

        $invoice->terms = trim($organisation->invoice_terms);
        $invoice->invoice_footer = trim($organisation->invoice_footer);

        $contact->email = 'contact@gmail.com';
        $relation->contacts = [$contact];

        $invoiceItem->cost = 100;
        $invoiceItem->qty = 1;
        $invoiceItem->notes = 'Notes';
        $invoiceItem->product_key = 'Item';

        $invoice->relation = $relation;
        $invoice->invoice_items = [$invoiceItem];

        $data['organisation'] = $organisation;
        $data['invoice'] = $invoice;
        $data['invoiceLabels'] = json_decode($organisation->invoice_labels) ?: [];
        $data['title'] = trans('texts.invoice_design');
        $data['invoiceDesigns'] = InvoiceDesign::getDesigns();
        $data['invoiceFonts'] = Cache::get('fonts');
        $data['section'] = $section;

        $design = false;
        foreach ($data['invoiceDesigns'] as $item) {
            if ($item->id == $organisation->invoice_design_id) {
                $design = $item->javascript;
                break;
            }
        }

        if ($section == ORGANISATION_CUSTOMIZE_DESIGN) {
            $data['customDesign'] = ($organisation->custom_design && !$design) ? $organisation->custom_design : $design;

            // sample invoice to help determine variables
            $invoice = Invoice::scope()
                            ->with('relation', 'organisation')
                            ->where('is_quote', '=', false)
                            ->where('is_recurring', '=', false)
                            ->first();

            if ($invoice) {
                $invoice->hidePrivateFields();
                unset($invoice->organisation);
                unset($invoice->invoice_items);
                unset($invoice->relation->contacts);
                $data['sampleInvoice'] = $invoice;
            }
        }

        return View::make("organisations.{$section}", $data);
    }

    private function showRelationPortal()
    {
        $organisation = Auth::user()->organisation->load('country');
        $css = $organisation->relation_view_css ? $organisation->relation_view_css : '';

        if (Utils::isNinja() && $css) {
            // Unescape the CSS for display purposes
            $css = str_replace(
                array('\3C ', '\3E ', '\26 '),
                array('<', '>', '&'),
                $css
            );
        }

        $data = [
            'relation_view_css' => $css,
            'enable_portal_password' => $organisation->enable_portal_password,
            'send_portal_password' => $organisation->send_portal_password,
            'title' => trans("texts.relation_portal"),
            'section' => ORGANISATION_RELATION_PORTAL,
            'organisation' => $organisation,
        ];

        return View::make("organisations.relation_portal", $data);
    }

    private function showTemplates()
    {
        $organisation = Auth::user()->organisation->load('country');
        $data['organisation'] = $organisation;
        $data['templates'] = [];
        $data['defaultTemplates'] = [];
        foreach ([ENTITY_INVOICE, ENTITY_QUOTE, ENTITY_PAYMENT, REMINDER1, REMINDER2, REMINDER3] as $type) {
            $data['templates'][$type] = [
                'subject' => $organisation->getEmailSubject($type),
                'template' => $organisation->getEmailTemplate($type),
            ];
            $data['defaultTemplates'][$type] = [
                'subject' => $organisation->getDefaultEmailSubject($type),
                'template' => $organisation->getDefaultEmailTemplate($type),
            ];
        }
        $data['emailFooter'] = $organisation->getEmailFooter();
        $data['title'] = trans('texts.email_templates');

        return View::make('organisations.templates_and_reminders', $data);
    }

    public function doSection($section = ORGANISATION_COMPANY_DETAILS)
    {
        if ($section === ORGANISATION_COMPANY_DETAILS) {
            return OrganisationController::saveDetails();
        } elseif ($section === ORGANISATION_LOCALIZATION) {
            return OrganisationController::saveLocalization();
        } elseif ($section === ORGANISATION_NOTIFICATIONS) {
            return OrganisationController::saveNotifications();
        } elseif ($section === ORGANISATION_EXPORT) {
            return OrganisationController::export();
        } elseif ($section === ORGANISATION_INVOICE_SETTINGS) {
            return OrganisationController::saveInvoiceSettings();
        } elseif ($section === ORGANISATION_EMAIL_SETTINGS) {
            return OrganisationController::saveEmailSettings();
        } elseif ($section === ORGANISATION_INVOICE_DESIGN) {
            return OrganisationController::saveInvoiceDesign();
        } elseif ($section === ORGANISATION_CUSTOMIZE_DESIGN) {
            return OrganisationController::saveCustomizeDesign();
        } elseif ($section === ORGANISATION_RELATION_PORTAL) {
            return OrganisationController::saveRelationPortal();
        } elseif ($section === ORGANISATION_TEMPLATES_AND_REMINDERS) {
            return OrganisationController::saveEmailTemplates();
        } elseif ($section === ORGANISATION_PRODUCTS) {
            return OrganisationController::saveProducts();
        } elseif ($section === ORGANISATION_TAX_RATES) {
            return OrganisationController::saveTaxRates();
        } elseif ($section === ORGANISATION_PAYMENT_TERMS) {
            return OrganisationController::savePaymetTerms();
        }
    }

    private function saveCustomizeDesign()
    {
        if (Auth::user()->organisation->isPro()) {
            $organisation = Auth::user()->organisation;
            $organisation->custom_design = Input::get('custom_design');
            $organisation->invoice_design_id = CUSTOM_DESIGN;
            $organisation->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('settings/'.ORGANISATION_CUSTOMIZE_DESIGN);
    }

    private function saveRelationPortal()
    {
        // Only allowed for pro Invoice Ninja users or white labeled self-hosted users
        if ((Utils::isNinja() && Auth::user()->organisation->isPro()) || Auth::user()->organisation->isWhiteLabel()) {
            $input_css = Input::get('relation_view_css');
            if (Utils::isNinja()) {
                // Allow referencing the body element
                $input_css = preg_replace('/(?<![a-z0-9\-\_\#\.])body(?![a-z0-9\-\_])/i', '.body', $input_css);

                //
                // Inspired by http://stackoverflow.com/a/5209050/1721527, dleavitt <https://stackoverflow.com/users/362110/dleavitt>
                //

                // Create a new configuration object
                $config = \HTMLPurifier_Config::createDefault();
                $config->set('Filter.ExtractStyleBlocks', true);
                $config->set('CSS.AllowImportant', true);
                $config->set('CSS.AllowTricky', true);
                $config->set('CSS.Trusted', true);

                // Create a new purifier instance
                $purifier = new \HTMLPurifier($config);

                // Wrap our CSS in style tags and pass to purifier.
                // we're not actually interested in the html response though
                $html = $purifier->purify('<style>'.$input_css.'</style>');

                // The "style" blocks are stored seperately
                $output_css = $purifier->context->get('StyleBlocks');

                // Get the first style block
                $sanitized_css = count($output_css) ? $output_css[0] : '';
            } else {
                $sanitized_css = $input_css;
            }

            $organisation = Auth::user()->organisation;
            $organisation->relation_view_css = $sanitized_css;

            $organisation->enable_relation_portal = !!Input::get('enable_relation_portal');
            $organisation->enable_portal_password = !!Input::get('enable_portal_password');
            $organisation->send_portal_password = !!Input::get('send_portal_password');

            $organisation->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('settings/'.ORGANISATION_RELATION_PORTAL);
    }

    private function saveEmailTemplates()
    {
        if (Auth::user()->organisation->isPro()) {
            $organisation = Auth::user()->organisation;

            foreach ([ENTITY_INVOICE, ENTITY_QUOTE, ENTITY_PAYMENT, REMINDER1, REMINDER2, REMINDER3] as $type) {
                $subjectField = "email_subject_{$type}";
                $subject = Input::get($subjectField, $organisation->getEmailSubject($type));
                $organisation->$subjectField = ($subject == $organisation->getDefaultEmailSubject($type) ? null : $subject);

                $bodyField = "email_template_{$type}";
                $body = Input::get($bodyField, $organisation->getEmailTemplate($type));
                $organisation->$bodyField = ($body == $organisation->getDefaultEmailTemplate($type) ? null : $body);
            }

            foreach ([REMINDER1, REMINDER2, REMINDER3] as $type) {
                $enableField = "enable_{$type}";
                $organisation->$enableField = Input::get($enableField) ? true : false;

                if ($organisation->$enableField) {
                    $organisation->{"num_days_{$type}"} = Input::get("num_days_{$type}");
                    $organisation->{"field_{$type}"} = Input::get("field_{$type}");
                    $organisation->{"direction_{$type}"} = Input::get("field_{$type}") == REMINDER_FIELD_INVOICE_DATE ? REMINDER_DIRECTION_AFTER : Input::get("direction_{$type}");
                }
            }

            $organisation->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('settings/'.ORGANISATION_TEMPLATES_AND_REMINDERS);
    }

    private function saveTaxRates()
    {
        $organisation = Auth::user()->organisation;

        $organisation->invoice_taxes = Input::get('invoice_taxes') ? true : false;
        $organisation->invoice_item_taxes = Input::get('invoice_item_taxes') ? true : false;
        $organisation->show_item_taxes = Input::get('show_item_taxes') ? true : false;
        $organisation->default_tax_rate_id = Input::get('default_tax_rate_id');
        $organisation->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/'.ORGANISATION_TAX_RATES);
    }

    private function saveProducts()
    {
        $organisation = Auth::user()->organisation;

        $organisation->fill_products = Input::get('fill_products') ? true : false;
        $organisation->update_products = Input::get('update_products') ? true : false;
        $organisation->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/'.ORGANISATION_PRODUCTS);
    }

    private function saveEmailSettings()
    {
        if (Auth::user()->organisation->isPro()) {
            $rules = [];
            $user = Auth::user();
            $iframeURL = preg_replace('/[^a-zA-Z0-9_\-\:\/\.]/', '', substr(strtolower(Input::get('iframe_url')), 0, MAX_IFRAME_URL_LENGTH));
            $iframeURL = rtrim($iframeURL, "/");

            $subdomain = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', substr(strtolower(Input::get('subdomain')), 0, MAX_SUBDOMAIN_LENGTH));
            if ($iframeURL) {
                $subdomain = null;
            }
            if ($subdomain) {
                $exclude = ['www', 'app', 'mail', 'admin', 'blog', 'user', 'contact', 'payment', 'payments', 'billing', 'invoice', 'business', 'owner', 'info', 'ninja'];
                $rules['subdomain'] = "unique:organisations,subdomain,{$user->organisation_id},id|not_in:" . implode(',', $exclude);
            }

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to('settings/'.ORGANISATION_EMAIL_SETTINGS)
                    ->withErrors($validator)
                    ->withInput();
            } else {
                $organisation = Auth::user()->organisation;
                $organisation->subdomain = $subdomain;
                $organisation->iframe_url = $iframeURL;
                $organisation->pdf_email_attachment = Input::get('pdf_email_attachment') ? true : false;
                $organisation->email_design_id = Input::get('email_design_id');

                if (Utils::isNinja()) {
                    $organisation->enable_email_markup = Input::get('enable_email_markup') ? true : false;
                }

                $organisation->save();
                Session::flash('message', trans('texts.updated_settings'));
            }
        }

        return Redirect::to('settings/'.ORGANISATION_EMAIL_SETTINGS);
    }

    private function saveInvoiceSettings()
    {
        if (Auth::user()->organisation->isPro()) {
            $rules = [
                'invoice_number_pattern' => 'has_counter',
                'quote_number_pattern' => 'has_counter',
            ];

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to('settings/'.ORGANISATION_INVOICE_SETTINGS)
                    ->withErrors($validator)
                    ->withInput();
            } else {
                $organisation = Auth::user()->organisation;
                $organisation->custom_label1 = trim(Input::get('custom_label1'));
                $organisation->custom_value1 = trim(Input::get('custom_value1'));
                $organisation->custom_label2 = trim(Input::get('custom_label2'));
                $organisation->custom_value2 = trim(Input::get('custom_value2'));
                $organisation->custom_relation_label1 = trim(Input::get('custom_relation_label1'));
                $organisation->custom_relation_label2 = trim(Input::get('custom_relation_label2'));
                $organisation->custom_invoice_label1 = trim(Input::get('custom_invoice_label1'));
                $organisation->custom_invoice_label2 = trim(Input::get('custom_invoice_label2'));
                $organisation->custom_invoice_taxes1 = Input::get('custom_invoice_taxes1') ? true : false;
                $organisation->custom_invoice_taxes2 = Input::get('custom_invoice_taxes2') ? true : false;
                $organisation->custom_invoice_text_label1 = trim(Input::get('custom_invoice_text_label1'));
                $organisation->custom_invoice_text_label2 = trim(Input::get('custom_invoice_text_label2'));
                $organisation->custom_invoice_item_label1 = trim(Input::get('custom_invoice_item_label1'));
                $organisation->custom_invoice_item_label2 = trim(Input::get('custom_invoice_item_label2'));

                $organisation->invoice_number_counter = Input::get('invoice_number_counter');
                $organisation->quote_number_prefix = Input::get('quote_number_prefix');
                $organisation->share_counter = Input::get('share_counter') ? true : false;
                $organisation->invoice_terms = Input::get('invoice_terms');
                $organisation->invoice_footer = Input::get('invoice_footer');
                $organisation->quote_terms = Input::get('quote_terms');
                $organisation->auto_convert_quote = Input::get('auto_convert_quote');
                $organisation->recurring_invoice_number_prefix = Input::get('recurring_invoice_number_prefix');

                if (Input::has('recurring_hour')) {
                    $organisation->recurring_hour = Input::get('recurring_hour');
                }

                if (!$organisation->share_counter) {
                    $organisation->quote_number_counter = Input::get('quote_number_counter');
                }

                if (Input::get('invoice_number_type') == 'prefix') {
                    $organisation->invoice_number_prefix = trim(Input::get('invoice_number_prefix'));
                    $organisation->invoice_number_pattern = null;
                } else {
                    $organisation->invoice_number_pattern = trim(Input::get('invoice_number_pattern'));
                    $organisation->invoice_number_prefix = null;
                }

                if (Input::get('quote_number_type') == 'prefix') {
                    $organisation->quote_number_prefix = trim(Input::get('quote_number_prefix'));
                    $organisation->quote_number_pattern = null;
                } else {
                    $organisation->quote_number_pattern = trim(Input::get('quote_number_pattern'));
                    $organisation->quote_number_prefix = null;
                }

                if (!$organisation->share_counter
                        && $organisation->invoice_number_prefix == $organisation->quote_number_prefix
                        && $organisation->invoice_number_pattern == $organisation->quote_number_pattern) {
                    Session::flash('error', trans('texts.invalid_counter'));

                    return Redirect::to('settings/'.ORGANISATION_INVOICE_SETTINGS)->withInput();
                } else {
                    $organisation->save();
                    Session::flash('message', trans('texts.updated_settings'));
                }
            }
        }

        return Redirect::to('settings/'.ORGANISATION_INVOICE_SETTINGS);
    }

    private function saveInvoiceDesign()
    {
        if (Auth::user()->organisation->isPro()) {
            $organisation = Auth::user()->organisation;
            $organisation->hide_quantity = Input::get('hide_quantity') ? true : false;
            $organisation->hide_paid_to_date = Input::get('hide_paid_to_date') ? true : false;
            $organisation->all_pages_header = Input::get('all_pages_header') ? true : false;
            $organisation->all_pages_footer = Input::get('all_pages_footer') ? true : false;
            $organisation->header_font_id = Input::get('header_font_id');
            $organisation->body_font_id = Input::get('body_font_id');
            $organisation->primary_color = Input::get('primary_color');
            $organisation->secondary_color = Input::get('secondary_color');
            $organisation->invoice_design_id = Input::get('invoice_design_id');

            if (Input::has('font_size')) {
                $organisation->font_size =  intval(Input::get('font_size'));
            }

            $labels = [];
            foreach (['item', 'description', 'unit_cost', 'quantity', 'line_total', 'terms', 'balance_due', 'partial_due'] as $field) {
                $labels[$field] = Input::get("labels_{$field}");
            }
            $organisation->invoice_labels = json_encode($labels);

            $organisation->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('settings/'.ORGANISATION_INVOICE_DESIGN);
    }

    private function saveNotifications()
    {
        $user = Auth::user();
        $user->notify_sent = Input::get('notify_sent');
        $user->notify_viewed = Input::get('notify_viewed');
        $user->notify_paid = Input::get('notify_paid');
        $user->notify_approved = Input::get('notify_approved');
        $user->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/'.ORGANISATION_NOTIFICATIONS);
    }

    public function updateDetails(UpdateOrganisationRequest $request)
    {
        $organisation = Auth::user()->organisation;
        $this->organisationRepo->save($request->input(), $organisation);

        /* Logo image file */
        if ($file = Input::file('logo')) {
            $path = Input::file('logo')->getRealPath();
            File::delete('logo/'.$organisation->organisation_key.'.jpg');
            File::delete('logo/'.$organisation->organisation_key.'.png');

            $mimeType = $file->getMimeType();

            if ($mimeType == 'image/jpeg') {
                $path = 'logo/'.$organisation->organisation_key.'.jpg';
                $file->move('logo/', $organisation->organisation_key.'.jpg');
            } elseif ($mimeType == 'image/png') {
                $path = 'logo/'.$organisation->organisation_key.'.png';
                $file->move('logo/', $organisation->organisation_key.'.png');
            } else {
                if (extension_loaded('fileinfo')) {
                    $image = Image::make($path);
                    $image->resize(200, 120, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $path = 'logo/'.$organisation->organisation_key.'.jpg';
                    Image::canvas($image->width(), $image->height(), '#FFFFFF')
                        ->insert($image)->save($path);
                } else {
                    Session::flash('warning', 'Warning: To support gifs the fileinfo PHP extension needs to be enabled.');
                }
            }

            // make sure image isn't interlaced
            if (extension_loaded('fileinfo')) {
                $img = Image::make($path);
                $img->interlace(false);
                $img->save();
            }
        }

        event(new UserSettingsChanged());

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/'.ORGANISATION_COMPANY_DETAILS);
    }

    public function saveUserDetails()
    {
        $user = Auth::user();
        $rules = ['email' => 'email|required|unique:users,email,'.$user->id.',id'];
        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('settings/'.ORGANISATION_USER_DETAILS)
                ->withErrors($validator)
                ->withInput();
        } else {
            $user->first_name = trim(Input::get('first_name'));
            $user->last_name = trim(Input::get('last_name'));
            $user->username = trim(Input::get('email'));
            $user->email = trim(strtolower(Input::get('email')));
            $user->phone = trim(Input::get('phone'));

            if (Utils::isNinja()) {
                if (Input::get('referral_code') && !$user->referral_code) {
                    $user->referral_code = $this->organisationRepo->getReferralCode();
                }
            }
            if (Utils::isNinjaDev()) {
                $user->dark_mode = Input::get('dark_mode') ? true : false;
            }

            $user->save();

            event(new UserSettingsChanged());
            Session::flash('message', trans('texts.updated_settings'));

            return Redirect::to('settings/'.ORGANISATION_USER_DETAILS);
        }
    }

    private function saveLocalization()
    {
        $organisation = Auth::user()->organisation;
        $organisation->timezone_id = Input::get('timezone_id') ? Input::get('timezone_id') : null;
        $organisation->date_format_id = Input::get('date_format_id') ? Input::get('date_format_id') : null;
        $organisation->datetime_format_id = Input::get('datetime_format_id') ? Input::get('datetime_format_id') : null;
        $organisation->currency_id = Input::get('currency_id') ? Input::get('currency_id') : 1; // US Dollar
        $organisation->language_id = Input::get('language_id') ? Input::get('language_id') : 1; // English
        $organisation->military_time = Input::get('military_time') ? true : false;
        $organisation->show_currency_code = Input::get('show_currency_code') ? true : false;
        $organisation->save();

        event(new UserSettingsChanged());

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/'.ORGANISATION_LOCALIZATION);
    }

    public function removeLogo()
    {
        File::delete('logo/'.Auth::user()->organisation->organisation_key.'.jpg');
        File::delete('logo/'.Auth::user()->organisation->organisation_key.'.png');

        Session::flash('message', trans('texts.removed_logo'));

        return Redirect::to('settings/'.ORGANISATION_COMPANY_DETAILS);
    }

    public function checkEmail()
    {
        $email = User::withTrashed()->where('email', '=', Input::get('email'))->where('id', '<>', Auth::user()->id)->first();

        if ($email) {
            return "taken";
        } else {
            return "available";
        }
    }

    public function submitSignup()
    {
        $rules = array(
            'new_first_name' => 'required',
            'new_last_name' => 'required',
            'new_password' => 'required|min:6',
            'new_email' => 'email|required|unique:users,email,'.Auth::user()->id.',id',
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return '';
        }

        $user = Auth::user();
        $user->first_name = trim(Input::get('new_first_name'));
        $user->last_name = trim(Input::get('new_last_name'));
        $user->email = trim(strtolower(Input::get('new_email')));
        $user->username = $user->email;
        $user->password = bcrypt(trim(Input::get('new_password')));
        $user->registered = true;
        $user->save();

        $user->organisation->startTrial();

        if (Input::get('go_pro') == 'true') {
            Session::set(REQUESTED_PRO_PLAN, true);
        }

        return "{$user->first_name} {$user->last_name}";
    }

    public function doRegister()
    {
        $affiliate = Affiliate::where('affiliate_key', '=', SELF_HOST_AFFILIATE_KEY)->first();
        $email = trim(Input::get('email'));

        if (!$email || $email == TEST_USERNAME) {
            return RESULT_FAILURE;
        }

        $license = new License();
        $license->first_name = Input::get('first_name');
        $license->last_name = Input::get('last_name');
        $license->email = $email;
        $license->transaction_reference = Request::getClientIp();
        $license->license_key = Utils::generateLicense();
        $license->affiliate_id = $affiliate->id;
        $license->product_id = PRODUCT_SELF_HOST;
        $license->is_claimed = 1;
        $license->save();

        return RESULT_SUCCESS;
    }

    public function cancelOrganisation()
    {
        if ($reason = trim(Input::get('reason'))) {
            $email = Auth::user()->email;
            $name = Auth::user()->getDisplayName();

            $data = [
                'text' => $reason,
            ];

            $subject = 'Invoice Ninja - Canceled Organisation';

            if (Auth::user()->isPaidPro()) {
                $subject .= ' [PRO]';
            }

            $this->userMailer->sendTo(CONTACT_EMAIL, $email, $name, $subject, 'contact', $data);
        }

        $user = Auth::user();
        $organisation = Auth::user()->organisation;
        \Log::info("Canceled Organisation: {$organisation->name} - {$user->email}");

        $this->organisationRepo->unlinkOrganisation($organisation);
        $organisation->forceDelete();

        Auth::logout();
        Session::flush();

        return Redirect::to('/')->with('clearGuestKey', true);
    }

    public function resendConfirmation()
    {
        $user = Auth::user();
        $this->userMailer->sendConfirmation($user);

        return Redirect::to('/settings/'.ORGANISATION_USER_DETAILS)->with('message', trans('texts.confirmation_resent'));
    }

    public function startTrial()
    {
        $user = Auth::user();

        if ($user->isEligibleForTrial()) {
            $user->organisation->startTrial();
        }

        return Redirect::back()->with('message', trans('texts.trial_success'));
    }

    public function redirectLegacy($section, $subSection = false)
    {
        if ($section === 'details') {
            $section = ORGANISATION_COMPANY_DETAILS;
        } elseif ($section === 'payments') {
            $section = ORGANISATION_PAYMENTS;
        } elseif ($section === 'advanced_settings') {
            $section = $subSection;
            if ($section === 'token_management') {
                $section = ORGANISATION_API_TOKENS;
            }
        }

        if (!in_array($section, array_merge(Organisation::$basicSettings, Organisation::$advancedSettings))) {
            $section = ORGANISATION_COMPANY_DETAILS;
        }

        return Redirect::to("/settings/$section/", 301);
    }
}
