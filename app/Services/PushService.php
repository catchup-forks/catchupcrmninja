<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Ninja\Notifications\PushFactory;
/**
 * Class PushService
 * @package App\Ninja\Notifications
 */


/**
 * $organisation->devices Definition
 *
 * @param string token (push notification device token)
 * @param string email (user email address - required for use as key)
 * @param string device (ios, gcm etc etc)
 * @param bool notify_sent
 * @param bool notify_paid
 * @param bool notify_approved
 * @param bool notify_viewed
 */

class PushService
{
    protected $pushFactory;

    /**
     * @param PushFactory $pushFactory
     */
    public function __construct(PushFactory $pushFactory)
    {
        $this->pushFactory = $pushFactory;
    }

    /**
     * @param $invoice - Invoice object
     * @param $type - Type of notification, ie. Quote APPROVED, Invoice PAID, Invoice/Quote SENT, Invoice/Quote VIEWED
     */

    public function sendNotification($invoice, $type)
    {
        //check user has registered for push notifications
        if(!$this->checkDeviceExists($invoice->organisation))
            return;

        //Harvest an array of devices that are registered for this notification type
        $devices = json_decode($invoice->organisation->devices, TRUE);

        foreach($devices as $device)
        {
            if(($device["notify_{$type}"] == TRUE) && ($device['device'] == 'ios'))
                $this->pushMessage($invoice, $device['token'], $type);
        }


    }


    /**
     * pushMessage function
     *
     * method to dispatch iOS notifications
     *
     * @param $invoice
     * @param $token
     * @param $type
     */
    private function pushMessage($invoice, $token, $type)
    {
        $this->pushFactory->message($token, $this->messageType($invoice, $type));
    }


    /**
     * checkDeviceExists function
     *
     * Returns a boolean if this organisation has devices registered for PUSH notifications
     *
     * @param $organisation
     * @return bool
     */
    private function checkDeviceExists($organisation)
    {
        $devices = json_decode($organisation->devices, TRUE);

        if(count($devices) >= 1)
            return TRUE;
        else
            return FALSE;
    }

    /**
     * messageType function
     *
     * method which formats an appropriate message depending on message type
     *
     * @param $invoice
     * @param $type
     * @return string
     */
    private function messageType($invoice, $type)
    {
        switch($type)
        {
            case 'sent':
                return $this->entitySentMessage($invoice);
                break;

            case 'paid':
                return $this->invoicePaidMessage($invoice);
                break;

            case 'approved':
                return $this->quoteApprovedMessage($invoice);
                break;

            case 'viewed':
                return $this->entityViewedMessage($invoice);
                break;
        }
    }

    /**
     * @param $invoice
     * @return string
     */
    private function entitySentMessage($invoice)
    {
        if($invoice->is_quote)
            return trans("texts.notification_quote_sent_subject", ['invoice' => $invoice->invoice_number, 'relation' => $invoice->relation->name]);
        else
            return trans("texts.notification_invoice_sent_subject", ['invoice' => $invoice->invoice_number, 'relation' => $invoice->relation->name]);

    }

    /**
     * @param $invoice
     * @return string
     */
    private function invoicePaidMessage($invoice)
    {
        return trans("texts.notification_invoice_paid_subject", ['invoice' => $invoice->invoice_number, 'relation' => $invoice->relation->name]);
    }

    /**
     * @param $invoice
     * @return string
     */
    private function quoteApprovedMessage($invoice)
    {
        return trans("texts.notification_quote_approved_subject", ['invoice' => $invoice->invoice_number, 'relation' => $invoice->relation->name]);
    }

    /**
     * @param $invoice
     * @return string
     */
    private function entityViewedMessage($invoice)
    {
        if($invoice->is_quote)
            return trans("texts.notification_quote_viewed_subject", ['invoice' => $invoice->invoice_number, 'relation' => $invoice->relation->name]);
        else
            return trans("texts.notification_invoice_viewed_subject", ['invoice' => $invoice->invoice_number, 'relation' => $invoice->relation->name]);

    }



}