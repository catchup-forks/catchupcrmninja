<?php namespace App\Http\Controllers;

use Auth;
use Input;
use Utils;
use Response;
use App\Models\Invoice;
use App\Ninja\Repositories\InvoiceRepository;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\QuoteTransformer;

class QuoteApiController extends BaseAPIController
{
    protected $invoiceRepo;

    public function __construct(InvoiceRepository $invoiceRepo)
    {
        parent::__construct();

        $this->invoiceRepo = $invoiceRepo;
    }

    /**
     * @SWG\Get(
     *   path="/quotes",
     *   tags={"quote"},
     *   summary="List of quotes",
     *   @SWG\Response(
     *     response=200,
     *     description="A list with quotes",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Invoice"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $paginator = Invoice::scope();
        $invoices = Invoice::scope()
                        ->with('relation', 'invitations', 'user', 'invoice_items')
                        ->where('invoices.is_quote', '=', true);

        if ($relationPublicId = Input::get('relation_id')) {
            $filter = function($query) use ($relationPublicId) {
                $query->where('public_id', '=', $relationPublicId);
            };
            $invoices->whereHas('relation', $filter);
            $paginator->whereHas('relation', $filter);
        }

        $invoices = $invoices->orderBy('created_at', 'desc')->paginate();

        $transformer = new QuoteTransformer(\Auth::user()->organisation, Input::get('serializer'));
        $paginator = $paginator->paginate();

        $data = $this->createCollection($invoices, $transformer, 'quotes', $paginator);

        return $this->response($data);
    }

  /*
  public function store()
  {
    $data = Input::all();
    $invoice = $this->invoiceRepo->save(false, $data, false);

    $response = json_encode($invoice, JSON_PRETTY_PRINT);
    $headers = Utils::getApiHeaders();
    return Response::make($response, 200, $headers);
  }
  */
}
