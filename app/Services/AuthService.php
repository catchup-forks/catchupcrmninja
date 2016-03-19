<?php namespace App\Services;

use Session;
use Auth;
use Utils;
use Input;
use Socialite;
use App\Ninja\Repositories\OrganisationRepository;
use App\Events\UserLoggedIn;
use App\Events\UserSignedUp;

class AuthService
{
    private $organisationRepo;

    public static $providers = [
        1 => SOCIAL_GOOGLE,
        2 => SOCIAL_FACEBOOK,
        3 => SOCIAL_GITHUB,
        4 => SOCIAL_LINKEDIN
    ];

    public function __construct(OrganisationRepository $repo)
    {
        $this->organisationRepo = $repo;
    }

    public static function getProviders()
    {
        $providers = [];

        
    }

    public function execute($provider, $hasCode)
    {
        if (!$hasCode) {
            return $this->getAuthorization($provider);
        }

        $socialiteUser = Socialite::driver($provider)->user();
        $providerId = AuthService::getProviderId($provider);

        if (Auth::check()) {
            $user = Auth::user();
            $isRegistered = $user->registered;

            $email = $socialiteUser->email;
            $oauthUserId = $socialiteUser->id;
            $name = Utils::splitName($socialiteUser->name);
            $result = $this->organisationRepo->updateUserFromOauth($user, $name[0], $name[1], $email, $providerId, $oauthUserId);

            if ($result === true) {
                if (!$isRegistered) {
                    Session::flash('warning', trans('texts.success_message'));
                    Session::flash('onReady', 'handleSignedUp();');
                } else {
                    Session::flash('message', trans('texts.updated_settings'));
                    return redirect()->to('/settings/' . ORGANISATION_USER_DETAILS);
                }
            } else {
                Session::flash('error', $result);
            }
        } else {
            if ($user = $this->organisationRepo->findUserByOauth($providerId, $socialiteUser->id)) {
                Auth::login($user, true);
                event(new UserLoggedIn());
            } else {
                Session::flash('error', trans('texts.invalid_credentials'));
                return redirect()->to('login');
            }
        }
        
        $redirectTo = Input::get('redirect_to') ?: 'dashboard';
        return redirect()->to($redirectTo);
    }

    private function getAuthorization($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public static function getProviderId($provider)
    {
        return array_search(strtolower($provider), array_map('strtolower', AuthService::$providers));
    }

    public static function getProviderName($providerId)
    {
        return $providerId ? AuthService::$providers[$providerId] : '';
    }
}
