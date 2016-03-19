<?php namespace App\Services;

use URL;
use App\Models\Gateway;
use App\Services\BaseService;
use App\Ninja\Repositories\OrganisationGatewayRepository;

class OrganisationGatewayService extends BaseService
{
    protected $OrganisationGatewayRepo;
    protected $datatableService;

    public function __construct(OrganisationGatewayRepository $OrganisationGatewayRepo, DatatableService $datatableService)
    {
        $this->OrganisationGatewayRepo = $OrganisationGatewayRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->OrganisationGatewayRepo;
    }

    /*
    public function save()
    {
        return null;
    }
    */

    public function getDatatable($organisationId)
    {
        $query = $this->OrganisationGatewayRepo->find($organisationId);

        return $this->createDatatable(ENTITY_ORGANISATION_GATEWAY, $query, false);
    }

    protected function getDatatableColumns($entityType, $hideRelation)
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("gateways/{$model->public_id}/edit", $model->name)->toHtml();
                }
            ],
            [
                'payment_type',
                function ($model) {
                    return Gateway::getPrettyPaymentType($model->gateway_id);
                }
            ],
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                uctrans('texts.edit_gateway'),
                function ($model) {
                    return URL::to("gateways/{$model->public_id}/edit");
                }
            ]
        ];
    }

}