<?php namespace App\Http\Controllers\Auth;

use Auth;
use Event;
use Utils;
use Session;
use Illuminate\Http\Request;
use App\Models\User;
use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use App\Ninja\Repositories\OrganisationRepository;
use App\Services\AuthService;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;

class AuthController extends Controller {

	/*
	|--------------------------------------------------------------------------
	| Registration & Login Controller
	|--------------------------------------------------------------------------
	|
	| This controller handles the registration of new users, as well as the
	| authentication of existing users. By default, this controller uses
	| a simple trait to add these behaviors. Why don't you explore it?
	|
	*/

	use AuthenticatesAndRegistersUsers;

    protected $redirectTo = '/dashboard';
    protected $authService;
    protected $organisationRepo;

	/**
	 * Create a new authentication controller instance.
	 *
	 * @param  \Illuminate\Contracts\Auth\Guard  $auth
	 * @param  \Illuminate\Contracts\Auth\Registrar  $registrar
	 * @return void
	 */
	public function __construct(OrganisationRepository $repo, AuthService $authService)
	{
        $this->organisationRepo = $repo;
        $this->authService = $authService;

		//$this->middleware('guest', ['except' => 'getLogout']);
	}

    public function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    public function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }

    public function authLogin($provider, Request $request)
    {
        return $this->authService->execute($provider, $request->has('code'));
    }

    public function authUnlink()
    {
        $this->organisationRepo->unlinkUserFromOauth(Auth::user());

        Session::flash('message', trans('texts.updated_settings'));
        return redirect()->to('/settings/' . ORGANISATION_USER_DETAILS);
    }

    public function getLoginWrapper()
    {
        if (!Utils::isNinja() && !User::count()) {
            return redirect()->to('invoice_now');
        }

        return self::getLogin();
    }

    public function postLoginWrapper(Request $request)
    {

        $userId = Auth::check() ? Auth::user()->id : null;
        $user = User::where('email', '=', $request->input('email'))->first();

        if ($user && $user->failed_logins >= MAX_FAILED_LOGINS) {
            Session::flash('error', trans('texts.invalid_credentials'));
            return redirect()->to('login');
        }


        $response = self::postLogin($request);

        if (Auth::check()) {
            Event::fire(new UserLoggedIn());

            $users = false;
            // we're linking a new organisation
            if ($request->link_organisations && $userId && Auth::user()->id != $userId) {
                $users = $this->organisationRepo->associateOrganisations($userId, Auth::user()->id);
                Session::flash('message', trans('texts.associated_accounts'));
            // check if other organisations are linked
            } else {
                $users = $this->organisationRepo->loadOrganisations(Auth::user()->id);
            }
            Session::put(SESSION_USER_ORGANISATIONS, $users);

        } elseif ($user) {
            $user->failed_logins = $user->failed_logins + 1;
            $user->save();
        }

        return $response;
    }


    public function getLogoutWrapper()
    {
        if (Auth::check() && !Auth::user()->registered) {
            $organisation = Auth::user()->organisation;
            $this->organisationRepo->unlinkOrganisation($organisation);
            $organisation->forceDelete();
        }

        $response = self::getLogout();

        Session::flush();

        return $response;
    }
}
