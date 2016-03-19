<?php namespace App\Ninja\Repositories;

use App\Models\Organisation;

class NinjaRepository
{
    public function updateProPlanPaid($relationPublicId, $proPlanPaid)
    {
        $organisation = Organisation::whereId($relationPublicId)->first();

        if (!$organisation) {
            return;
        }

        $organisation->pro_plan_paid = $proPlanPaid;
        $organisation->save();
    }
}
