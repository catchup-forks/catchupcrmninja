<?php namespace App\Ninja\Transformers;

use App\Models\OrganisationToken;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class OrganisationTokenTransformer extends TransformerAbstract
{

    public function transform(OrganisationToken $account_token)
    {
        return [
            'name' => $account_token->name,
            'token' => $account_token->token
        ];
    }
}