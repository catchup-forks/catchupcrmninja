<?php namespace App\Ninja\Repositories;

use App\Models\Organisation;

class NinjaRepository
{
    public function updateProPlanPaid($clientPublicId, $proPlanPaid)
    {
        $organisation = Account::whereId($clientPublicId)->first();

        if (!$organisation) {
            return;
        }

        $organisation->pro_plan_paid = $proPlanPaid;
        $organisation->save();
    }
}
