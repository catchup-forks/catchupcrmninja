<?php namespace App\Ninja\Transformers;

use App\Models\Organisation;
use App\Models\Client;
use League\Fractal\TransformerAbstract;

class EntityTransformer extends TransformerAbstract
{
    protected $organisation;
    protected $serializer;

    public function __construct(Organisation $organisation = null, $serializer = null)
    {
        $this->organisation = $organisation;
        $this->serializer = $serializer;
    }

    protected function includeCollection($data, $transformer, $entityType)
    {
        if ($this->serializer && $this->serializer != API_SERIALIZER_JSON) {
            $entityType = null;
        }

        return $this->collection($data, $transformer, $entityType);
    }

    protected function includeItem($data, $transformer, $entityType)
    {
        if ($this->serializer && $this->serializer != API_SERIALIZER_JSON) {
            $entityType = null;
        }

        return $this->item($data, $transformer, $entityType);
    }

    protected function getTimestamp($date)
    {
        return $date ? $date->getTimestamp() : null;
    }
}
