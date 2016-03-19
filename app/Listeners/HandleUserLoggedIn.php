<?php namespace App\Listeners;

use Utils;
use Auth;
use Carbon;
use Session;
use App\Events\UserLoggedIn;
use App\Events\UserSignedUp;
use App\Ninja\Repositories\OrganisationRepository;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeQueued;

class HandleUserLoggedIn {

    protected $accountRepo;

	/**
	 * Create the event handler.
	 *
	 * @return void
	 */
	public function __construct(OrganisationRepository $accountRepo)
	{
        $this->accountRepo = $accountRepo;
	}

	/**
	 * Handle the event.
	 *
	 * @param  UserLoggedIn  $event
	 * @return void
	 */
	public function handle(UserLoggedIn $event)
	{
        $organisation = Auth::user()->organisation;

        if (empty($organisation->last_login)) {
            event(new UserSignedUp());
        }

        $organisation->last_login = Carbon::now()->toDateTimeString();
        $organisation->save();

        $users = $this->accountRepo->loadAccounts(Auth::user()->id);
        Session::put(SESSION_USER_ORGANISATIONS, $users);

        $organisation->loadLocalizationSettings();

        // if they're using Stripe make sure they're using Stripe.js 
        $OrganisationGateway = $organisation->getGatewayConfig(GATEWAY_STRIPE);
        if ($OrganisationGateway && ! $OrganisationGateway->getPublishableStripeKey()) {
            Session::flash('warning', trans('texts.missing_publishable_key'));
        } elseif ($organisation->isLogoTooLarge()) {
            Session::flash('warning', trans('texts.logo_too_large', ['size' => $organisation->getLogoSize() . 'KB']));
        }
	}

}
