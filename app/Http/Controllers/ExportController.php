<?php namespace App\Http\Controllers;

use Auth;
use Excel;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use App\Ninja\Serializers\ArraySerializer;
use App\Ninja\Transformers\OrganisationTransformer;
use App\Models\Relation;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Vendor;
use App\Models\VendorContact;

class ExportController extends BaseController
{
    public function doExport(Request $request)
    {
        $format = $request->input('format');
        $date = date('Y-m-d');
        $fileName = "invoice-ninja-{$date}";

        if ($format === 'JSON') {
            return $this->returnJSON($request, $fileName);
        } elseif ($format === 'CSV') {
            return $this->returnCSV($request, $fileName);
        } else {
            return $this->returnXLS($request, $fileName);
        }
    }

    private function returnJSON($request, $fileName)
    {
        $output = fopen('php://output', 'w') or Utils::fatalError();
        header('Content-Type:application/json');
        header("Content-Disposition:attachment;filename={$fileName}.json");

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());

        $organisation = Auth::user()->organisation;
        $organisation->loadAllData();

        $resource = new Item($organisation, new OrganisationTransformer);
        $data = $manager->createData($resource)->toArray();

        return response()->json($data);
    }


    private function returnCSV($request, $fileName)
    {
        $data = $this->getData($request);

        return Excel::create($fileName, function($excel) use ($data) {
            $excel->sheet('', function($sheet) use ($data) {
                $sheet->loadView('export', $data);
            });
        })->download('csv');
    }

    private function returnXLS($request, $fileName)
    {
        $user = Auth::user();
        $data = $this->getData($request);

        return Excel::create($fileName, function($excel) use ($user, $data) {

            $excel->setTitle($data['title'])
                  ->setCreator($user->getDisplayName())
                  ->setLastModifiedBy($user->getDisplayName())
                  ->setDescription('')
                  ->setSubject('')
                  ->setKeywords('')
                  ->setCategory('')
                  ->setManager('')
                  ->setCompany($user->organisation->getDisplayName());

            foreach ($data as $key => $val) {
                if ($key === 'organisation' || $key === 'title' || $key === 'multiUser') {
                    continue;
                }
                $label = trans("texts.{$key}");
                $excel->sheet($label, function($sheet) use ($key, $data) {
                    if ($key === 'quotes') {
                        $key = 'invoices';
                        $data['entityType'] = ENTITY_QUOTE;
                    } elseif ($key === 'recurringInvoices') {
                        $key = 'recurring_invoices';
                    }
                    $sheet->loadView("export.{$key}", $data);
                });
            }
        })->download('xls');
    }

    private function getData($request)
    {
        $organisation = Auth::user()->organisation;

        $data = [
            'organisation' => $organisation,
            'title' => 'Invoice Ninja v' . NINJA_VERSION . ' - ' . $organisation->formatDateTime($organisation->getDateTime()),
            'multiUser' => $organisation->users->count() > 1
        ];
        
        if ($request->input(ENTITY_RELATION)) {
            $data['relations'] = Relation::scope()
                ->with('user', 'contacts', 'country')
                ->withArchived()
                ->get();

            $data['contacts'] = Contact::scope()
                ->with('user', 'relation.contacts')
                ->withTrashed()
                ->get();

            $data['credits'] = Credit::scope()
                ->with('user', 'relation.contacts')
                ->get();
        }
        
        if ($request->input(ENTITY_TASK)) {
            $data['tasks'] = Task::scope()
                ->with('user', 'relation.contacts')
                ->withArchived()
                ->get();
        }
        
        if ($request->input(ENTITY_INVOICE)) {
            $data['invoices'] = Invoice::scope()
                ->with('user', 'relation.contacts', 'invoice_status')
                ->withArchived()
                ->where('is_quote', '=', false)
                ->where('is_recurring', '=', false)
                ->get();
        
            $data['quotes'] = Invoice::scope()
                ->with('user', 'relation.contacts', 'invoice_status')
                ->withArchived()
                ->where('is_quote', '=', true)
                ->where('is_recurring', '=', false)
                ->get();

            $data['recurringInvoices'] = Invoice::scope()
                ->with('user', 'relation.contacts', 'invoice_status', 'frequency')
                ->withArchived()
                ->where('is_quote', '=', false)
                ->where('is_recurring', '=', true)
                ->get();
        }
        
        if ($request->input(ENTITY_PAYMENT)) {
            $data['payments'] = Payment::scope()
                ->withArchived()
                ->with('user', 'relation.contacts', 'payment_type', 'invoice', 'account_gateway.gateway')
                ->get();
        }

        
        if ($request->input(ENTITY_VENDOR)) {
            $data['relations'] = Vendor::scope()
                ->with('user', 'vendorcontacts', 'country')
                ->withArchived()
                ->get();

            $data['vendor_contacts'] = VendorContact::scope()
                ->with('user', 'vendor.contacts')
                ->withTrashed()
                ->get();
            
            /*
            $data['expenses'] = Credit::scope()
                ->with('user', 'relation.contacts')
                ->get();
            */
        }
        
        return $data;
    }
}