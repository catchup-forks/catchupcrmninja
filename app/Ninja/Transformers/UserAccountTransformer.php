<?php namespace App\Ninja\Transformers;

use App\Models\User;
use App\Models\Organisation;
use League\Fractal;
use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Item;

class UserOrganisationTransformer extends EntityTransformer
{
    protected $defaultIncludes = [
        'user'
    ];

    protected $tokenName;
    
    public function __construct(Organisation $organisation, $serializer, $tokenName)
    {
        parent::__construct($organisation, $serializer);

        $this->tokenName = $tokenName;
    }

    public function includeUser(User $user)
    {
        $transformer = new UserTransformer($this->organisation, $this->serializer);
        return $this->includeItem($user, $transformer, 'user');
    }

    public function transform(User $user)
    {
        return [
            'organisation_key' => $user->organisation->organisation_key,
            'name' => $user->organisation->present()->name,
            'token' => $user->organisation->getToken($this->tokenName),
            'default_url' => SITE_URL
        ];
    }
}