<?php namespace App\Listeners;

use Auth;
use Session;
use App\Events\UserSettingsChanged;
use App\Ninja\Repositories\OrganisationRepository;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeQueued;
use App\Ninja\Mailers\UserMailer;

class HandleUserSettingsChanged {

	/**
	 * Create the event handler.
	 *
	 * @return void
	 */
	public function __construct(OrganisationRepository $accountRepo, UserMailer $userMailer)
	{
        $this->accountRepo = $accountRepo;
        $this->userMailer = $userMailer;
	}

	/**
	 * Handle the event.
	 *
	 * @param  UserSettingsChanged  $event
	 * @return void
	 */
	public function handle(UserSettingsChanged $event)
	{
        if (!Auth::check()) {
            return;
        }

        $organisation = Auth::user()->organisation;
        $organisation->loadLocalizationSettings();

        $users = $this->accountRepo->loadAccounts(Auth::user()->id);
        Session::put(SESSION_USER_ORGANISATIONS, $users);

        if ($event->user && $event->user->isEmailBeingChanged()) {
            $this->userMailer->sendConfirmation($event->user);
            Session::flash('warning', trans('texts.verify_email'));
        }
	}

}
