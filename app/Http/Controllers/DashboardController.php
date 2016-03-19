<?php namespace App\Http\Controllers;

use Auth;
use DB;
use View;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;

class DashboardController extends BaseController
{
    public function index()
    {
        $view_all = !Auth::user()->hasPermission('view_all');
        $user_id = Auth::user()->id;
            
        // total_income, billed_relations, invoice_sent and active_relations
        $select = DB::raw('COUNT(DISTINCT CASE WHEN invoices.id IS NOT NULL THEN relations.id ELSE null END) billed_relations,
                        SUM(CASE WHEN invoices.invoice_status_id >= '.INVOICE_STATUS_SENT.' THEN 1 ELSE 0 END) invoices_sent,
                        COUNT(DISTINCT relations.id) active_relations');
        $metrics = DB::table('organisations')
            ->select($select)
            ->leftJoin('relations', 'organisations.id', '=', 'relations.organisation_id')
            ->leftJoin('invoices', 'relations.id', '=', 'invoices.relation_id')
            ->where('organisations.id', '=', Auth::user()->organisation_id)
            ->where('relations.is_deleted', '=', false)
            ->where('invoices.is_deleted', '=', false)
            ->where('invoices.is_recurring', '=', false)
            ->where('invoices.is_quote', '=', false);
            
        if(!$view_all){
            $metrics = $metrics->where(function($query) use($user_id){
                $query->where('invoices.user_id', '=', $user_id);
                $query->orwhere(function($query) use($user_id){
                    $query->where('invoices.user_id', '=', null); 
                    $query->where('relations.user_id', '=', $user_id);
                });
            });
        }
            
        $metrics = $metrics->groupBy('organisations.id')
            ->first();

        $select = DB::raw('SUM(relations.paid_to_date) as value, relations.currency_id as currency_id');
        $paidToDate = DB::table('organisations')
            ->select($select)
            ->leftJoin('relations', 'organisations.id', '=', 'relations.organisation_id')
            ->where('organisations.id', '=', Auth::user()->organisation_id)
            ->where('relations.is_deleted', '=', false);
            
        if(!$view_all){
            $paidToDate = $paidToDate->where('relations.user_id', '=', $user_id);
        }
        
        $paidToDate = $paidToDate->groupBy('organisations.id')
            ->groupBy(DB::raw('CASE WHEN relations.currency_id IS NULL THEN CASE WHEN organisations.currency_id IS NULL THEN 1 ELSE organisations.currency_id END ELSE relations.currency_id END'))
            ->get();

        $select = DB::raw('AVG(invoices.amount) as invoice_avg, relations.currency_id as currency_id');
        $averageInvoice = DB::table('organisations')
            ->select($select)
            ->leftJoin('relations', 'organisations.id', '=', 'relations.organisation_id')
            ->leftJoin('invoices', 'relations.id', '=', 'invoices.relation_id')
            ->where('organisations.id', '=', Auth::user()->organisation_id)
            ->where('relations.is_deleted', '=', false)
            ->where('invoices.is_deleted', '=', false)
            ->where('invoices.is_quote', '=', false)
            ->where('invoices.is_recurring', '=', false);
            
        if(!$view_all){
            $averageInvoice = $averageInvoice->where('invoices.user_id', '=', $user_id);
        }
        
        $averageInvoice = $averageInvoice->groupBy('organisations.id')
            ->groupBy(DB::raw('CASE WHEN relations.currency_id IS NULL THEN CASE WHEN organisations.currency_id IS NULL THEN 1 ELSE organisations.currency_id END ELSE relations.currency_id END'))
            ->get();

        $select = DB::raw('SUM(relations.balance) as value, relations.currency_id as currency_id');
        $balances = DB::table('organisations')
            ->select($select)
            ->leftJoin('relations', 'organisations.id', '=', 'relations.organisation_id')
            ->where('organisations.id', '=', Auth::user()->organisation_id)
            ->where('relations.is_deleted', '=', false)
            ->groupBy('organisations.id')
            ->groupBy(DB::raw('CASE WHEN relations.currency_id IS NULL THEN CASE WHEN organisations.currency_id IS NULL THEN 1 ELSE organisations.currency_id END ELSE relations.currency_id END'))
            ->get();

        $activities = Activity::where('activities.organisation_id', '=', Auth::user()->organisation_id)
                ->where('activities.activity_type_id', '>', 0);
        
        if(!$view_all){
            $activities = $activities->where('activities.user_id', '=', $user_id);
        }
                
        $activities = $activities->orderBy('activities.created_at', 'desc')
                ->with('relation.contacts', 'user', 'invoice', 'payment', 'credit', 'organisation')
                ->take(50)
                ->get();

        $pastDue = DB::table('invoices')
                    ->leftJoin('relations', 'relations.id', '=', 'invoices.relation_id')
                    ->leftJoin('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->where('invoices.organisation_id', '=', Auth::user()->organisation_id)
                    ->where('relations.deleted_at', '=', null)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.is_recurring', '=', false)
                    //->where('invoices.is_quote', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('invoices.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)
                    ->where('invoices.due_date', '<', date('Y-m-d'));
            
        if(!$view_all){
            $pastDue = $pastDue->where('invoices.user_id', '=', $user_id);
        }
            
        $pastDue = $pastDue->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'relations.name as relation_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'relations.currency_id', 'relations.public_id as relation_public_id', 'relations.user_id as relation_user_id', 'is_quote'])
                    ->orderBy('invoices.due_date', 'asc')
                    ->take(50)
                    ->get();

        $upcoming = DB::table('invoices')
                    ->leftJoin('relations', 'relations.id', '=', 'invoices.relation_id')
                    ->leftJoin('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->where('invoices.organisation_id', '=', Auth::user()->organisation_id)
                    ->where('relations.deleted_at', '=', null)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.deleted_at', '=', null)
                    ->where('invoices.is_recurring', '=', false)
                    //->where('invoices.is_quote', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('contacts.is_primary', '=', true)
                    ->where('invoices.due_date', '>=', date('Y-m-d'))
                    ->orderBy('invoices.due_date', 'asc');
            
        if(!$view_all){
            $upcoming = $upcoming->where('invoices.user_id', '=', $user_id);
        }
            
        $upcoming = $upcoming->take(50)
                    ->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'relations.name as relation_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'relations.currency_id', 'relations.public_id as relation_public_id', 'relations.user_id as relation_user_id', 'is_quote'])
                    ->get();

        $payments = DB::table('payments')
                    ->leftJoin('relations', 'relations.id', '=', 'payments.relation_id')
                    ->leftJoin('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->where('payments.organisation_id', '=', Auth::user()->organisation_id)
                    ->where('payments.is_deleted', '=', false)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('relations.is_deleted', '=', false)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true);
            
        if(!$view_all){
            $payments = $payments->where('payments.user_id', '=', $user_id);
        }
            
        $payments = $payments->select(['payments.payment_date', 'payments.amount', 'invoices.public_id', 'invoices.invoice_number', 'relations.name as relation_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'relations.currency_id', 'relations.public_id as relation_public_id', 'relations.user_id as relation_user_id'])
                    ->orderBy('payments.payment_date', 'desc')
                    ->take(50)
                    ->get();

        $hasQuotes = false;
        foreach ([$upcoming, $pastDue] as $data) {
            foreach ($data as $invoice) {
                if ($invoice->is_quote) {
                    $hasQuotes = true;
                }
            }
        }

        $data = [
            'organisation' => Auth::user()->organisation,
            'paidToDate' => $paidToDate,
            'balances' => $balances,
            'averageInvoice' => $averageInvoice,
            'invoicesSent' => $metrics ? $metrics->invoices_sent : 0,
            'activeRelations' => $metrics ? $metrics->active_relations : 0,
            'activities' => $activities,
            'pastDue' => $pastDue,
            'upcoming' => $upcoming,
            'payments' => $payments,
            'title' => trans('texts.dashboard'),
            'hasQuotes' => $hasQuotes,
        ];

        return View::make('dashboard', $data);
    }
}
