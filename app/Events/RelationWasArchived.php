<?php namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;

class RelationWasArchived extends Event
{
    use SerializesModels;

    public $relation;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($relation)
    {
        $this->relation = $relation;
    }
}
