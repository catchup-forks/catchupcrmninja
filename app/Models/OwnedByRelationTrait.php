<?php namespace App\Models;

trait OwnedByRelationTrait
{
    public function isRelationTrashed()
    {
        if (!$this->relation) {
            return false;
        }

        return $this->relation->trashed();
    }
}