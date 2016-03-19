<?php namespace App\Console\Commands;

use DB;
use DateTime;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use App\Models\Organisation;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\OrganisationRepository;
use App\Ninja\Repositories\InvoiceRepository;

class SendReminders extends Command
{
    protected $name = 'ninja:send-reminders';
    protected $description = 'Send reminder emails';
    protected $mailer;
    protected $invoiceRepo;
    protected $accountRepo;

    public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, OrganisationRepository $accountRepo)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->invoiceRepo = $invoiceRepo;
        $this->accountRepo = $accountRepo;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' Running SendReminders...');
        $today = new DateTime();

        $accounts = $this->accountRepo->findWithReminders();
        $this->info(count($accounts).' organisations found');

        foreach ($accounts as $organisation) {
            if (!$organisation->isPro()) {
                continue;
            }

            $invoices = $this->invoiceRepo->findNeedingReminding($organisation);
            $this->info($organisation->name . ': ' . count($invoices).' invoices found');

            foreach ($invoices as $invoice) {
                if ($reminder = $organisation->getInvoiceReminder($invoice)) {
                    $this->info('Send to ' . $invoice->id);
                    $this->mailer->sendInvoice($invoice, $reminder);
                }
            }
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
