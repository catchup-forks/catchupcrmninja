<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\OrganisationRepository;
use App\Services\PaymentService;
use App\Models\Invoice;

class ChargeRenewalInvoices extends Command
{
    protected $name = 'ninja:charge-renewals';
    protected $description = 'Charge renewal invoices';
    
    protected $mailer;
    protected $accountRepo;
    protected $paymentService;

    public function __construct(Mailer $mailer, OrganisationRepository $repo, PaymentService $paymentService)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->accountRepo = $repo;
        $this->paymentService = $paymentService;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' ChargeRenewalInvoices...');

        $organisation = $this->accountRepo->getNinjaAccount();
        $invoices = Invoice::whereOrganisationId($organisation->id)
                        ->whereDueDate(date('Y-m-d'))
                        ->with('client')
                        ->orderBy('id')
                        ->get();

        $this->info(count($invoices).' invoices found');

        foreach ($invoices as $invoice) {
            $this->info("Charging invoice {$invoice->invoice_number}");
            $this->paymentService->autoBillInvoice($invoice);
        }

        $this->info('Done');
    }

    protected function getArguments()
    {
        return array(
            //array('example', InputArgument::REQUIRED, 'An example argument.'),
        );
    }

    protected function getOptions()
    {
        return array(
            //array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
        );
    }
}
