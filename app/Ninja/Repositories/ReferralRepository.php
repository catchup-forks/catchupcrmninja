<?php namespace App\Ninja\Repositories;

use DB;
use Utils;

class ReferralRepository
{
    public function getCounts($userId)
    {
        $accounts = DB::table('organisations')
                        ->where('referral_user_id', $userId)
                        ->get(['id', 'pro_plan_paid']);

        $counts = [
            'free' => 0,
            'pro' => 0
        ];

        foreach ($accounts as $organisation) {
            $counts['free']++;
            if (Utils::withinPastYear($organisation->pro_plan_paid)) {
                $counts['pro']++;
            }
        }

        return $counts;
    }



}