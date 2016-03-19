<?php namespace App\Ninja\Transformers;

use App\Models\OrganisationToken;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class OrganisationTokenTransformer extends TransformerAbstract
{

    public function transform(OrganisationToken $organisation_token)
    {
        return [
            'name' => $organisation_token->name,
            'token' => $organisation_token->token
        ];
    }
}