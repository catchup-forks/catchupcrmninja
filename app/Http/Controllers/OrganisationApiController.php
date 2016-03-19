<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use Response;
use Input;
use Validator;
use Cache;
use App\Models\Relation;
use App\Models\Organisation;
use App\Models\OrganisationToken;
use App\Ninja\Repositories\OrganisationRepository;
use Illuminate\Http\Request;
use League\Fractal;
use League\Fractal\Manager;
use App\Ninja\Serializers\ArraySerializer;
use App\Ninja\Transformers\OrganisationTransformer;
use App\Ninja\Transformers\UserOrganisationTransformer;
use App\Http\Controllers\BaseAPIController;
use Swagger\Annotations as SWG;

use App\Events\UserSignedUp;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateOrganisationRequest;

class OrganisationApiController extends BaseAPIController
{
    protected $organisationRepo;

    public function __construct(OrganisationRepository $organisationRepo)
    {
        parent::__construct();

        $this->organisationRepo = $organisationRepo;
    }

    public function register(RegisterRequest $request)
    {

        $organisation = $this->organisationRepo->create($request->first_name, $request->last_name, $request->email, $request->password);
        $user = $organisation->users()->first();
        
        Auth::login($user, true);
        event(new UserSignedUp());
        
        return $this->processLogin($request);
    }

    public function login(Request $request)
    {
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return $this->processLogin($request);
        } else {
            sleep(ERROR_DELAY);
            return $this->errorResponse(['message'=>'Invalid credentials'],401);
        }
    }

    private function processLogin(Request $request)
    {
        // Create a new token only if one does not already exist
        $user = Auth::user();
        $this->organisationRepo->createTokens($user, $request->token_name);

        $users = $this->organisationRepo->findUsers($user, 'organisation.account_tokens');
        $transformer = new UserOrganisationTransformer($user->organisation, $request->serializer, $request->token_name);
        $data = $this->createCollection($users, $transformer, 'user_account');

        return $this->response($data);
    }

    public function show(Request $request)
    {
        $organisation = Auth::user()->organisation;
        $updatedAt = $request->updated_at ? date('Y-m-d H:i:s', $request->updated_at) : false;

        $organisation->loadAllData($updatedAt);

        $transformer = new OrganisationTransformer(null, $request->serializer);
        $organisation = $this->createItem($organisation, $transformer, 'organisation');

        return $this->response($organisation);
    }

    public function getStaticData()
    {
        $data = [];

        $cachedTables = unserialize(CACHED_TABLES);
        foreach ($cachedTables as $name => $class) {
            $data[$name] = Cache::get($name);
        }

        return $this->response($data);
    }

    public function getUserOrganisations(Request $request)
    {
        return $this->processLogin($request);
    }

    public function update(UpdateOrganisationRequest $request)
    {
        $organisation = Auth::user()->organisation;
        $this->organisationRepo->save($request->input(), $organisation);

        $transformer = new OrganisationTransformer(null, $request->serializer);
        $organisation = $this->createItem($organisation, $transformer, 'organisation');

        return $this->response($organisation);
    }

    public function addDeviceToken(Request $request)
    {
        $organisation = Auth::user()->organisation;

        //scan if this user has a token already registered (tokens can change, so we need to use the users email as key)
        $devices = json_decode($organisation->devices,TRUE);


            for($x=0; $x<count($devices); $x++)
            {
                if ($devices[$x]['email'] == Auth::user()->username) {
                    $devices[$x]['token'] = $request->token; //update
                    $organisation->devices = json_encode($devices);
                    $organisation->save();
                    $devices[$x]['organisation_key'] = $organisation->organisation_key;

                    return $this->response($devices[$x]);
                }
            }

        //User does not have a device, create new record

        $newDevice = [
            'token' => $request->token,
            'email' => $request->email,
            'device' => $request->device,
            'organisation_key' => $organisation->organisation_key,
            'notify_sent' => TRUE,
            'notify_viewed' => TRUE,
            'notify_approved' => TRUE,
            'notify_paid' => TRUE,
        ];

        $devices[] = $newDevice;
        $organisation->devices = json_encode($devices);
        $organisation->save();

        return $this->response($newDevice);

    }

    public function updatePushNotifications(Request $request)
    {
        $organisation = Auth::user()->organisation;

        $devices = json_decode($organisation->devices, TRUE);

        if(count($devices) < 1)
            return $this->errorResponse(['message'=>'No registered devices.'], 400);

        for($x=0; $x<count($devices); $x++)
        {
            if($devices[$x]['email'] == Auth::user()->username)
            {

                $newDevice = [
                    'token' => $devices[$x]['token'],
                    'email' => $devices[$x]['email'],
                    'device' => $devices[$x]['device'],
                    'organisation_key' => $organisation->organisation_key,
                    'notify_sent' => $request->notify_sent,
                    'notify_viewed' => $request->notify_viewed,
                    'notify_approved' => $request->notify_approved,
                    'notify_paid' => $request->notify_paid,
                ];

                //unset($devices[$x]);

                $devices[$x] = $newDevice;
                $organisation->devices = json_encode($devices);
                $organisation->save();

                return $this->response($newDevice);
            }
        }

    }
}
