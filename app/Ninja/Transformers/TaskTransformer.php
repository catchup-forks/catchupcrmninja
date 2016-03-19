<?php namespace App\Ninja\Transformers;

use App\Models\Organisation;
use App\Models\Task;
use App\Models\Relation;
use League\Fractal;

/**
 * @SWG\Definition(definition="Task", @SWG\Xml(name="Task"))
 */

class TaskTransformer extends EntityTransformer
{
    /**
    * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="amount", type="float", example=10, readOnly=true)
    * @SWG\Property(property="invoice_id", type="integer", example=1)
    */
    protected $availableIncludes = [
        'relation',
    ];


    public function __construct(Organisation $organisation)
    {
        parent::__construct($organisation);

    }

    public function includeRelation(Task $task)
    {
        if ($task->relation) {
            $transformer = new RelationTransformer($this->organisation, $this->serializer);
            return $this->includeItem($task->relation, $transformer, 'relation');
        } else {
            return null;
        }
    }

    public function transform(Task $task)
    {
        return [
            'id' => (int) $task->public_id,
            'organisation_key' => $this->organisation->organisation_key,
            'user_id' => (int) $task->user->public_id + 1,
            'description' => $task->description,
            'duration' => $task->getDuration()
        ];
    }
}