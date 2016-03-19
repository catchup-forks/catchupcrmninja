<?php namespace App\Services;

use Utils;
use URL;
use Auth;
use App\Services\BaseService;
use App\Models\Relation;
use App\Models\Invoice;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Task;
use App\Ninja\Repositories\RelationRepository;
use App\Ninja\Repositories\NinjaRepository;

class RelationService extends BaseService
{
    protected $relationRepo;
    protected $datatableService;

    public function __construct(RelationRepository $relationRepo, DatatableService $datatableService, NinjaRepository $ninjaRepo)
    {
        $this->relationRepo = $relationRepo;
        $this->ninjaRepo = $ninjaRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->relationRepo;
    }

    public function save($data)
    {
        if (Auth::user()->organisation->isNinjaOrganisation() && isset($data['pro_plan_paid'])) {
            $this->ninjaRepo->updateProPlanPaid($data['public_id'], $data['pro_plan_paid']);
        }

        return $this->relationRepo->save($data);
    }

    public function getDatatable($search)
    {
        $query = $this->relationRepo->find($search);

        if(!Utils::hasPermission('view_all')){
            $query->where('relations.user_id', '=', Auth::user()->id);
        }

        return $this->createDatatable(ENTITY_RELATION, $query);
    }

    protected function getDatatableColumns($entityType, $hideRelation)
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("relations/{$model->public_id}", $model->name ?: '')->toHtml();
                }
            ],
            [
                'first_name',
                function ($model) {
                    return link_to("relations/{$model->public_id}", $model->first_name.' '.$model->last_name)->toHtml();
                }
            ],
            [
                'email',
                function ($model) {
                    return link_to("relations/{$model->public_id}", $model->email ?: '')->toHtml();
                }
            ],
            [
                'relations.created_at',
                function ($model) {
                    return Utils::timestampToDateString(strtotime($model->created_at));
                }
            ],
            [
                'last_login',
                function ($model) {
                    return Utils::timestampToDateString(strtotime($model->last_login));
                }
            ],
            [
                'balance',
                function ($model) {
                    return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.edit_relation'),
                function ($model) {
                    return URL::to("relations/{$model->public_id}/edit");
                },
                function ($model) {
                    return Relation::canEditItem($model);
                }
            ],
            [
                '--divider--', function(){return false;},
                function ($model) {
                    return Relation::canEditItem($model) && (Task::canCreate() || Invoice::canCreate());
                }
            ],
            [
                trans('texts.new_task'),
                function ($model) {
                    return URL::to("tasks/create/{$model->public_id}");
                },
                function ($model) {
                    return Task::canCreate();
                }
            ],
            [
                trans('texts.new_invoice'),
                function ($model) {
                    return URL::to("invoices/create/{$model->public_id}");
                },
                function ($model) {
                    return Invoice::canCreate();
                }
            ],
            [
                trans('texts.new_quote'),
                function ($model) {
                    return URL::to("quotes/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->isPro() && Invoice::canCreate();
                }
            ],
            [
                '--divider--', function(){return false;},
                function ($model) {
                    return (Task::canCreate() || Invoice::canCreate()) && (Payment::canCreate() || Credit::canCreate() || Expense::canCreate());
                }
            ],
            [
                trans('texts.enter_payment'),
                function ($model) {
                    return URL::to("payments/create/{$model->public_id}");
                },
                function ($model) {
                    return Payment::canCreate();
                }
            ],
            [
                trans('texts.enter_credit'),
                function ($model) {
                    return URL::to("credits/create/{$model->public_id}");
                },
                function ($model) {
                    return Credit::canCreate();
                }
            ],
            [
                trans('texts.enter_expense'),
                function ($model) {
                    return URL::to("expenses/create/0/{$model->public_id}");
                },
                function ($model) {
                    return Expense::canCreate();
                }
            ]
        ];
    }
}
