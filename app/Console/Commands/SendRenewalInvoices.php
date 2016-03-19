<?php namespace App\Console\Commands;

use DB;
use DateTime;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use App\Models\Organisation;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\OrganisationRepository;

class SendRenewalInvoices extends Command
{
    protected $name = 'ninja:send-renewals';
    protected $description = 'Send renewal invoices';
    protected $mailer;
    protected $accountRepo;

    public function __construct(Mailer $mailer, OrganisationRepository $repo)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->accountRepo = $repo;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' Running SendRenewalInvoices...');
        $today = new DateTime();
        $sentTo = [];

        // get all organisations with pro plans expiring in 10 days
        $accounts = Organisation::whereRaw('datediff(curdate(), pro_plan_paid) = 355')
                        ->orderBy('id')
                        ->get();
        $this->info(count($accounts).' organisations found');

        foreach ($accounts as $organisation) {
            // don't send multiple invoices to multi-company users
            if ($userAccountId = $this->accountRepo->getUserAccountId($organisation)) {
                if (isset($sentTo[$userAccountId])) {
                    continue;
                } else {
                    $sentTo[$userAccountId] = true;
                }
            }

            $client = $this->accountRepo->getNinjaClient($organisation);
            $invitation = $this->accountRepo->createNinjaInvoice($client, $organisation);

            // set the due date to 10 days from now
            $invoice = $invitation->invoice;
            $invoice->due_date = date('Y-m-d', strtotime('+ 10 days'));
            $invoice->save();

            $this->mailer->sendInvoice($invoice);
            $this->info("Sent invoice to {$client->getDisplayName()}");
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
