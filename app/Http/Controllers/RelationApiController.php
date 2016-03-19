<?php namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Utils;
use Response;
use Input;
use Auth;
use App\Models\Relation;
use App\Ninja\Repositories\RelationRepository;
use App\Http\Requests\CreateRelationRequest;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\RelationTransformer;
use App\Services\RelationService;
use App\Http\Requests\UpdateRelationRequest;

class RelationApiController extends BaseAPIController
{
    protected $relationRepo;
    protected $relationService;

    public function __construct(RelationRepository $relationRepo, RelationService $relationService)
    {
        parent::__construct();

        $this->relationRepo = $relationRepo;
        $this->relationService = $relationService;
    }

    public function ping()
    {
        $headers = Utils::getApiHeaders();

        return Response::make('', 200, $headers);
    }

    /**
     * @SWG\Get(
     *   path="/relations",
     *   summary="List of relations",
     *   tags={"relation"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with relations",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $relations = Relation::scope()
            ->with($this->getIncluded())
            ->orderBy('created_at', 'desc')->withTrashed();

        // Filter by email
        if (Input::has('email')) {

            $email = Input::get('email');
            $relations = $relations->whereHas('contacts', function ($query) use ($email) {
                $query->where('email', $email);
            });

        }

        $relations = $relations->paginate();

        $transformer = new RelationTransformer(Auth::user()->organisation, Input::get('serializer'));
        $paginator = Relation::scope()->withTrashed()->paginate();

        $data = $this->createCollection($relations, $transformer, ENTITY_RELATION, $paginator);

        return $this->response($data);
    }

    /**
     * @SWG\Post(
     *   path="/relations",
     *   tags={"relation"},
     *   summary="Create a relation",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Relation")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New relation",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateRelationRequest $request)
    {
        $relation = $this->relationRepo->save($request->input());

        $relation = Relation::scope($relation->public_id)
            ->with('country', 'contacts', 'industry', 'size', 'currency')
            ->first();

        $transformer = new RelationTransformer(Auth::user()->organisation, Input::get('serializer'));
        $data = $this->createItem($relation, $transformer, ENTITY_RELATION);

        return $this->response($data);
    }

    /**
     * @SWG\Put(
     *   path="/relations/{relation_id}",
     *   tags={"relation"},
     *   summary="Update a relation",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Relation")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Update relation",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function update(UpdateRelationRequest $request, $publicId)
    {
        if ($request->action == ACTION_ARCHIVE) {


            $relation = Relation::scope($publicId)->withTrashed()->first();

            if(!$relation)
                return $this->errorResponse(['message'=>'Record not found'], 400);

            $this->relationRepo->archive($relation);

            $transformer = new RelationTransformer(Auth::user()->organisation, Input::get('serializer'));
            $data = $this->createItem($relation, $transformer, ENTITY_RELATION);

            return $this->response($data);
        }
        else if ($request->action == ACTION_RESTORE){

            $relation = Relation::scope($publicId)->withTrashed()->first();

            if(!$relation)
                return $this->errorResponse(['message'=>'Relation not found.'], 400);

            $this->relationRepo->restore($relation);

            $transformer = new RelationTransformer(Auth::user()->organisation, Input::get('serializer'));
            $data = $this->createItem($relation, $transformer, ENTITY_RELATION);

            return $this->response($data);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $this->relationRepo->save($data);

        $relation = Relation::scope($publicId)
            ->with('country', 'contacts', 'industry', 'size', 'currency')
            ->first();

        if(!$relation)
            return $this->errorResponse(['message'=>'Relation not found.'],400);

        $transformer = new RelationTransformer(Auth::user()->organisation, Input::get('serializer'));
        $data = $this->createItem($relation, $transformer, ENTITY_RELATION);

        return $this->response($data);
    }


    /**
     * @SWG\Delete(
     *   path="/relations/{relation_id}",
     *   tags={"relation"},
     *   summary="Delete a relation",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Relation")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Delete relation",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function destroy($publicId)
    {

        $relation = Relation::scope($publicId)->withTrashed()->first();
        $this->relationRepo->delete($relation);

        $relation = Relation::scope($publicId)
            ->with('country', 'contacts', 'industry', 'size', 'currency')
            ->withTrashed()
            ->first();

        $transformer = new RelationTransformer(Auth::user()->organisation, Input::get('serializer'));
        $data = $this->createItem($relation, $transformer, ENTITY_RELATION);

        return $this->response($data);

    }


}
