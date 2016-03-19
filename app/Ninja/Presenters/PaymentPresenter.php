<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class PaymentPresenter extends Presenter {

    public function relation()
    {
        return $this->entity->relation ? $this->entity->relation->getDisplayName() : '';
    }

    public function payment_date()
    {
        return Utils::fromSqlDate($this->entity->payment_date);
    }

    public function method()
    {
        if ($this->entity->account_gateway) {
            return $this->entity->account_gateway->gateway->name;
        } elseif ($this->entity->payment_type) {
            return $this->entity->payment_type->name;
        }
    }

}