<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use Session;
use App\Models\OrganisationGateway;
use App\Ninja\Repositories\BaseRepository;

class OrganisationGatewayRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\OrganisationGateway';
    }

    public function find($organisationId)
    {
        return DB::table('organisation_gateways')
                    ->join('gateways', 'gateways.id', '=', 'organisation_gateways.gateway_id')
                    ->where('organisation_gateways.deleted_at', '=', null)
                    ->where('organisation_gateways.organisation_id', '=', $organisationId)
                    ->select('organisation_gateways.public_id', 'gateways.name', 'organisation_gateways.deleted_at', 'organisation_gateways.gateway_id');
    }
}
