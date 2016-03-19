<?php namespace App\Services;

use URL;
use Auth;
use Utils;
use App\Models\Invoice;
use App\Ninja\Repositories\InvoiceRepository;

class RecurringInvoiceService extends BaseService
{
    protected $invoiceRepo;
    protected $datatableService;

    public function __construct(InvoiceRepository $invoiceRepo, DatatableService $datatableService)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->datatableService = $datatableService;
    }

    public function getDatatable($organisationId, $relationPublicId = null, $entityType, $search)
    {
        $query = $this->invoiceRepo->getRecurringInvoices($organisationId, $relationPublicId, $search);

        if(!Utils::hasPermission('view_all')){
            $query->where('invoices.user_id', '=', Auth::user()->id);
        }
        
        return $this->createDatatable(ENTITY_RECURRING_INVOICE, $query, !$relationPublicId);
    }

    protected function getDatatableColumns($entityType, $hideRelation)
    {
        return [
            [
                'frequency',
                function ($model) {
                    return link_to("invoices/{$model->public_id}", $model->frequency)->toHtml();
                }
            ],
            [
                'relation_name',
                function ($model) {
                    return link_to("relations/{$model->relation_public_id}", Utils::getRelationDisplayName($model))->toHtml();
                },
                ! $hideRelation
            ],
            [
                'start_date',
                function ($model) {
                    return Utils::fromSqlDate($model->start_date);
                }
            ],
            [
                'end_date',
                function ($model) {
                    return Utils::fromSqlDate($model->end_date);
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.edit_invoice'),
                function ($model) {
                    return URL::to("invoices/{$model->public_id}/edit");
                },
                function ($model) {
                    return Invoice::canEditItem($model);
                }
            ]
        ];
    }
}