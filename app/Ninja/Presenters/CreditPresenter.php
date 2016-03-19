<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class CreditPresenter extends Presenter {

    public function relation()
    {
        return $this->entity->relation ? $this->entity->relation->getDisplayName() : '';
    }

    public function credit_date()
    {
        return Utils::fromSqlDate($this->entity->credit_date);
    }
}