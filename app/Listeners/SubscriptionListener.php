<?php namespace app\Listeners;

use Auth;
use Utils;

use App\Events\RelationWasCreated;
use App\Events\QuoteWasCreated;
use App\Events\InvoiceWasCreated;
use App\Events\CreditWasCreated;
use App\Events\PaymentWasCreated;

use App\Events\VendorWasCreated;
use App\Events\ExpenseWasCreated;

use App\Ninja\Transformers\InvoiceTransformer;
use App\Ninja\Transformers\RelationTransformer;
use App\Ninja\Transformers\PaymentTransformer;

use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use App\Ninja\Serializers\ArraySerializer;

class SubscriptionListener
{
    public function createdRelation(RelationWasCreated $event)
    {
        $transformer = new RelationTransformer($event->relation->organisation);
        $this->checkSubscriptions(EVENT_CREATE_RELATION, $event->relation, $transformer);
    }

    public function createdQuote(QuoteWasCreated $event)
    {
        $transformer = new InvoiceTransformer($event->quote->organisation);
        $this->checkSubscriptions(EVENT_CREATE_QUOTE, $event->quote, $transformer, ENTITY_RELATION);
    }

    public function createdPayment(PaymentWasCreated $event)
    {
        $transformer = new PaymentTransformer($event->payment->organisation);
        $this->checkSubscriptions(EVENT_CREATE_PAYMENT, $event->payment, $transformer, [ENTITY_RELATION, ENTITY_INVOICE]);
    }

    public function createdInvoice(InvoiceWasCreated $event)
    {
        $transformer = new InvoiceTransformer($event->invoice->organisation);
        $this->checkSubscriptions(EVENT_CREATE_INVOICE, $event->invoice, $transformer, ENTITY_RELATION);
    }

    public function createdCredit(CreditWasCreated $event)
    {
        
    }

    public function createdVendor(VendorWasCreated $event)
    {

    }

    public function createdExpense(ExpenseWasCreated $event)
    {

    }

    private function checkSubscriptions($eventId, $entity, $transformer, $include = '')
    {
        $subscription = $entity->organisation->getSubscription($eventId);

        if ($subscription) {
            $manager = new Manager();
            $manager->setSerializer(new ArraySerializer());
            $manager->parseIncludes($include);

            $resource = new Item($entity, $transformer, $entity->getEntityType());
            $data = $manager->createData($resource)->toArray();

            // For legacy Zapier support
            if (isset($data['relation_id'])) {
                $data['relation_name'] = $entity->relation->getDisplayName();
            }

            Utils::notifyZapier($subscription, $data);
        }
    }
}
