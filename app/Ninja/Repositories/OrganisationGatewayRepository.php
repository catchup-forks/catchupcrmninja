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
        return DB::table('account_gateways')
                    ->join('gateways', 'gateways.id', '=', 'account_gateways.gateway_id')
                    ->where('account_gateways.deleted_at', '=', null)
                    ->where('account_gateways.organisation_id', '=', $organisationId)
                    ->select('account_gateways.public_id', 'gateways.name', 'account_gateways.deleted_at', 'account_gateways.gateway_id');
    }
}
