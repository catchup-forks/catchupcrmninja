<?php namespace App\Http\Controllers;

use Auth;
use Datatable;
use Utils;
use View;
use URL;
use Validator;
use Input;
use Session;
use Redirect;
use Cache;

use App\Models\Activity;
use App\Models\Relation;
use App\Models\Organisation;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Size;
use App\Models\PaymentTerm;
use App\Models\Industry;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Country;
use App\Models\Task;
use App\Ninja\Repositories\RelationRepository;
use App\Services\RelationService;

use App\Http\Requests\CreateRelationRequest;
use App\Http\Requests\UpdateRelationRequest;

class RelationController extends BaseController
{
    protected $relationService;
    protected $relationRepo;
    protected $model = 'App\Models\Relation';

    public function __construct(RelationRepository $relationRepo, RelationService $relationService)
    {
        //parent::__construct();

        $this->relationRepo = $relationRepo;
        $this->relationService = $relationService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list', array(
            'entityType' => ENTITY_RELATION,
            'title' => trans('texts.relations'),
            'sortCol' => '4',
            'columns' => Utils::trans([
              'checkbox',
              'relation',
              'contact',
              'email',
              'date_created',
              'last_login',
              'balance',
              ''
            ]),
        ));
    }

    public function getDatatable()
    {
        return $this->relationService->getDatatable(Input::get('sSearch'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(CreateRelationRequest $request)
    {
        $data = $request->input();
        
        if(!$this->checkUpdatePermission($data, $response)){
            return $response;
        }
                
        $relation = $this->relationService->save($data);

        Session::flash('message', trans('texts.created_relation'));

        return redirect()->to($relation->getRoute());
    }

    /**
     * Display the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function show($publicId)
    {
        $relation = Relation::withTrashed()->scope($publicId)->with('contacts', 'size', 'industry')->firstOrFail();
        
        if(!$this->checkViewPermission($relation, $response)){
            return $response;
        }
        
        Utils::trackViewed($relation->getDisplayName(), ENTITY_RELATION);

        $actionLinks = [];
        if(Task::canCreate()){
            $actionLinks[] = ['label' => trans('texts.new_task'), 'url' => '/tasks/create/'.$relation->public_id];
        }
        if (Utils::isPro() && Invoice::canCreate()) {
            $actionLinks[] = ['label' => trans('texts.new_quote'), 'url' => '/quotes/create/'.$relation->public_id];
        }
        
        if(!empty($actionLinks)){
            $actionLinks[] = \DropdownButton::DIVIDER;
        }
        
        if(Payment::canCreate()){
            $actionLinks[] = ['label' => trans('texts.enter_payment'), 'url' => '/payments/create/'.$relation->public_id];
        }
        
        if(Credit::canCreate()){
            $actionLinks[] = ['label' => trans('texts.enter_credit'), 'url' => '/credits/create/'.$relation->public_id];
        }
        
        if(Expense::canCreate()){
            $actionLinks[] = ['label' => trans('texts.enter_expense'), 'url' => '/expenses/create/0/'.$relation->public_id];
        }

        $data = array(
            'actionLinks' => $actionLinks,
            'showBreadcrumbs' => false,
            'relation' => $relation,
            'credit' => $relation->getTotalCredit(),
            'title' => trans('texts.view_relation'),
            'hasRecurringInvoices' => Invoice::scope()->where('is_recurring', '=', true)->whereRelationId($relation->id)->count() > 0,
            'hasQuotes' => Invoice::scope()->where('is_quote', '=', true)->whereRelationId($relation->id)->count() > 0,
            'hasTasks' => Task::scope()->whereRelationId($relation->id)->count() > 0,
            'gatewayLink' => $relation->getGatewayLink(),
        );

        return View::make('relations.show', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if(!$this->checkCreatePermission($response)){
            return $response;
        }
        
        if (Relation::scope()->withTrashed()->count() > Auth::user()->getMaxNumRelations()) {
            return View::make('error', ['hideHeader' => true, 'error' => "Sorry, you've exceeded the limit of ".Auth::user()->getMaxNumRelations()." relations"]);
        }

        $data = [
            'relation' => null,
            'method' => 'POST',
            'url' => 'relations',
            'title' => trans('texts.new_relation'),
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('relations.edit', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function edit($publicId)
    {
        $relation = Relation::scope($publicId)->with('contacts')->firstOrFail();
        
        if(!$this->checkEditPermission($relation, $response)){
            return $response;
        }
        
        $data = [
            'relation' => $relation,
            'method' => 'PUT',
            'url' => 'relations/'.$publicId,
            'title' => trans('texts.edit_relation'),
        ];

        $data = array_merge($data, self::getViewModel());

        if (Auth::user()->organisation->isNinjaOrganisation()) {
            if ($organisation = Organisation::whereId($relation->public_id)->first()) {
                $data['proPlanPaid'] = $organisation['pro_plan_paid'];
            }
        }

        return View::make('relations.edit', $data);
    }

    private static function getViewModel()
    {
        return [
            'data' => Input::old('data'),
            'organisation' => Auth::user()->organisation,
            'sizes' => Cache::get('sizes'),
            'paymentTerms' => Cache::get('paymentTerms'),
            'industries' => Cache::get('industries'),
            'currencies' => Cache::get('currencies'),
            'languages' => Cache::get('languages'),
            'countries' => Cache::get('countries'),
            'customLabel1' => Auth::user()->organisation->custom_relation_label1,
            'customLabel2' => Auth::user()->organisation->custom_relation_label2,
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update(UpdateRelationRequest $request)
    {
        $data = $request->input();
        
        if(!$this->checkUpdatePermission($data, $response)){
            return $response;
        }
                
        $relation = $this->relationService->save($data);

        Session::flash('message', trans('texts.updated_relation'));

        return redirect()->to($relation->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->relationService->bulk($ids, $action);

        $message = Utils::pluralize($action.'d_relation', $count);
        Session::flash('message', $message);

        if ($action == 'restore' && $count == 1) {
            return Redirect::to('relations/'.Utils::getFirst($ids));
        } else {
            return Redirect::to('relations');
        }
    }
}
