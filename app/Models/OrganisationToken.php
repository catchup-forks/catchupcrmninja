<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class OrganisationToken extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function getEntityType()
    {
        return ENTITY_TOKEN;
    }

    public function organisation()
    {
        return $this->belongsTo('App\Models\Organisation');
    }
}
