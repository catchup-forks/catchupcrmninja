<?php namespace app\Listeners;

use Utils;
use Auth;
use App\Events\UserSignedUp;
use App\Models\Activity;
use App\Ninja\Repositories\OrganisationRepository;
use App\Ninja\Mailers\UserMailer;

class HandleUserSignedUp
{
    protected $organisationRepo;
    protected $userMailer;

    /**
     * Create the event handler.
     *
     * @return void
     */
    public function __construct(OrganisationRepository $organisationRepo, UserMailer $userMailer)
    {
        $this->organisationRepo = $organisationRepo;
        $this->userMailer = $userMailer;
    }

    /**
     * Handle the event.
     *
     * @param  UserSignedUp $event
     * @return void
     */
    public function handle(UserSignedUp $event)
    {
        $user = Auth::user();

        if (Utils::isNinjaProd()) {
            $this->userMailer->sendConfirmation($user);
        } elseif (Utils::isNinjaDev()) {
            // do nothing
        } else {
            $this->organisationRepo->registerNinjaUser($user);
        }

        session([SESSION_COUNTER => -1]);
    }
}
