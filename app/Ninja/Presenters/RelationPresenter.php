<?php namespace App\Ninja\Presenters;

use URL;
use Utils;
use Laracasts\Presenter\Presenter;

class RelationPresenter extends Presenter {

    public function country()
    {
        return $this->entity->country ? $this->entity->country->name : '';
    }

    public function status()
    {
        $class = $text = '';

        if ($this->entity->is_deleted) {
            $class = 'danger';
            $text = trans('texts.deleted');
        } elseif ($this->entity->trashed()) {
            $class = 'warning';
            $text = trans('texts.archived');
        } else {
            $class = 'success';
            $text = trans('texts.active');
        }

        return "<span class=\"label label-{$class}\">{$text}</span>";
    }

    public function url()
    {
        return URL::to('/relations/' . $this->entity->public_id);
    }

    public function link()
    {
        return link_to('/relations/' . $this->entity->public_id, $this->entity->getDisplayName());
    }
}