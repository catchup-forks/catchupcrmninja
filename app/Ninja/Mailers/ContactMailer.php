<?php namespace App\Ninja\Mailers;

use Form;
use Utils;
use Event;
use URL;
use Auth;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Activity;
use App\Models\Gateway;

use App\Events\InvoiceWasEmailed;
use App\Events\QuoteWasEmailed;

class ContactMailer extends Mailer
{
    public static $variableFields = [
        'footer',
        'organisation',
        'dueDate',
        'invoiceDate',
        'relation',
        'amount',
        'contact',
        'firstName',
        'invoice',
        'quote',
        'password',
        'viewLink',
        'viewButton',
        'paymentLink',
        'paymentButton',
    ];

    public function sendInvoice(Invoice $invoice, $reminder = false, $pdfString = false)
    {
        $invoice->load('invitations', 'relation.language', 'organisation');
        $entityType = $invoice->getEntityType();

        $relation = $invoice->relation;
        $organisation = $invoice->organisation;

        $response = null;

        if ($relation->trashed()) {
            return trans('texts.email_errors.inactive_relation');
        } elseif ($invoice->trashed()) {
            return trans('texts.email_errors.inactive_invoice');
        }

        $organisation->loadLocalizationSettings($relation);
        $emailTemplate = $organisation->getEmailTemplate($reminder ?: $entityType);
        $emailSubject = $organisation->getEmailSubject($reminder ?: $entityType);

        $sent = false;

        if ($organisation->attatchPDF() && !$pdfString) {
            $pdfString = $invoice->getPDFString();
        }

        foreach ($invoice->invitations as $invitation) {
            $response = $this->sendInvitation($invitation, $invoice, $emailTemplate, $emailSubject, $pdfString);
            if ($response === true) {
                $sent = true;
            }
        }
        
        $organisation->loadLocalizationSettings();

        if ($sent === true) {
            if ($invoice->is_quote) {
                event(new QuoteWasEmailed($invoice));
            } else {
                event(new InvoiceWasEmailed($invoice));
            }
        }

        return $response;
    }

    private function sendInvitation($invitation, $invoice, $body, $subject, $pdfString)
    {
        $relation = $invoice->relation;
        $organisation = $invoice->organisation;
        
        if (Auth::check()) {
            $user = Auth::user();
        } else {
            $user = $invitation->user;
            if ($invitation->user->trashed()) {
                $user = $organisation->users()->orderBy('id')->first();
            }
        }

        if (!$user->email || !$user->registered) {
            return trans('texts.email_errors.user_unregistered');
        } elseif (!$user->confirmed) {
            return trans('texts.email_errors.user_unconfirmed');
        } elseif (!$invitation->contact->email) {
            return trans('texts.email_errors.invalid_contact_email');
        } elseif ($invitation->contact->trashed()) {
            return trans('texts.email_errors.inactive_contact');
        }

        $variables = [
            'organisation' => $organisation,
            'relation' => $relation,
            'invitation' => $invitation,
            'amount' => $invoice->getRequestedAmount()
        ];
        
         if (empty($invitation->contact->password) && $organisation->isPro() && $organisation->enable_portal_password && $organisation->send_portal_password) {
            // The contact needs a password
            $variables['password'] = $password = $this->generatePassword();
            $invitation->contact->password = bcrypt($password);
            $invitation->contact->save();
        }

        $data = [
            'body' => $this->processVariables($body, $variables),
            'link' => $invitation->getLink(),
            'entityType' => $invoice->getEntityType(),
            'invoiceId' => $invoice->id,
            'invitation' => $invitation,
            'organisation' => $organisation,
            'relation' => $relation,
            'invoice' => $invoice,
        ];

        if ($organisation->attatchPDF()) {
            $data['pdfString'] = $pdfString;
            $data['pdfFileName'] = $invoice->getFileName();
        }

        $subject = $this->processVariables($subject, $variables);
        $fromEmail = $user->email;

        if ($organisation->getEmailDesignId() == EMAIL_DESIGN_PLAIN) {
            $view = ENTITY_INVOICE;
        } else {
            $view = 'design' . ($organisation->getEmailDesignId() - 1);
        }
        
        $response = $this->sendTo($invitation->contact->email, $fromEmail, $organisation->getDisplayName(), $subject, $view, $data);

        if ($response === true) {
            return true;
        } else {
            return $response;
        }
    }
    
    protected function generatePassword($length = 9)
    {
        $sets = array(
            'abcdefghjkmnpqrstuvwxyz',
            'ABCDEFGHJKMNPQRSTUVWXYZ',
            '23456789',
        );
        $all = '';
        $password = '';
        foreach($sets as $set)
        {
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }
        $all = str_split($all);
        for($i = 0; $i < $length - count($sets); $i++)
            $password .= $all[array_rand($all)];
        $password = str_shuffle($password);
        
        return $password;
    }

    public function sendPaymentConfirmation(Payment $payment)
    {
        $organisation = $payment->organisation;
        $relation = $payment->relation;

        $organisation->loadLocalizationSettings($relation);

        $invoice = $payment->invoice;
        $organisationName = $organisation->getDisplayName();
        $emailTemplate = $organisation->getEmailTemplate(ENTITY_PAYMENT);
        $emailSubject = $invoice->organisation->getEmailSubject(ENTITY_PAYMENT);

        if ($payment->invitation) {
            $user = $payment->invitation->user;
            $contact = $payment->contact;
            $invitation = $payment->invitation;
        } else {
            $user = $payment->user;
            $contact = $relation->contacts[0];
            $invitation = $payment->invoice->invitations[0];
        }

        $variables = [
            'organisation' => $organisation,
            'relation' => $relation,
            'invitation' => $invitation,
            'amount' => $payment->amount,
        ];

        $data = [
            'body' => $this->processVariables($emailTemplate, $variables),
            'link' => $invitation->getLink(),
            'invoice' => $invoice,
            'relation' => $relation,
            'organisation' => $organisation,
            'payment' => $payment,
            'entityType' => ENTITY_INVOICE,
        ];

        if ($organisation->attatchPDF()) {
            $data['pdfString'] = $invoice->getPDFString();
            $data['pdfFileName'] = $invoice->getFileName();
        }

        $subject = $this->processVariables($emailSubject, $variables);
        $data['invoice_id'] = $payment->invoice->id;

        if ($organisation->getEmailDesignId() == EMAIL_DESIGN_PLAIN) {
            $view = 'payment_confirmation';
        } else {
            $view = 'design' . ($organisation->getEmailDesignId() - 1);
        }

        if ($user->email && $contact->email) {
            $this->sendTo($contact->email, $user->email, $organisationName, $subject, $view, $data);
        }

        $organisation->loadLocalizationSettings();
    }

    public function sendLicensePaymentConfirmation($name, $email, $amount, $license, $productId)
    {
        $view = 'license_confirmation';
        $subject = trans('texts.payment_subject');
        
        if ($productId == PRODUCT_ONE_CLICK_INSTALL) {
            $license = "Softaculous install license: $license";
        } elseif ($productId == PRODUCT_INVOICE_DESIGNS) {
            $license = "Invoice designs license: $license";
        } elseif ($productId == PRODUCT_WHITE_LABEL) {
            $license = "White label license: $license";
        }
        
        $data = [
            'relation' => $name,
            'amount' => Utils::formatMoney($amount, DEFAULT_CURRENCY, DEFAULT_COUNTRY),
            'license' => $license
        ];
        
        $this->sendTo($email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    private function processVariables($template, $data)
    {
        $organisation = $data['organisation'];
        $relation = $data['relation'];
        $invitation = $data['invitation'];
        $invoice = $invitation->invoice;
        $passwordHTML = isset($data['password'])?'<p>'.trans('texts.password').': '.$data['password'].'<p>':false;

        $variables = [
            '$footer' => $organisation->getEmailFooter(),
            '$relation' => $relation->getDisplayName(),
            '$organisation' => $organisation->getDisplayName(),
            '$dueDate' => $organisation->formatDate($invoice->due_date),
            '$invoiceDate' => $organisation->formatDate($invoice->invoice_date),
            '$contact' => $invitation->contact->getDisplayName(),
            '$firstName' => $invitation->contact->first_name,
            '$amount' => $organisation->formatMoney($data['amount'], $relation),
            '$invoice' => $invoice->invoice_number,
            '$quote' => $invoice->invoice_number,
            '$link' => $invitation->getLink(),
            '$password' => $passwordHTML,
            '$viewLink' => $invitation->getLink().'$password',
            '$viewButton' => Form::emailViewButton($invitation->getLink(), $invoice->getEntityType()).'$password',
            '$paymentLink' => $invitation->getLink('payment').'$password',
            '$paymentButton' => Form::emailPaymentButton($invitation->getLink('payment')).'$password',
            '$customRelation1' => $organisation->custom_relation_label1,
            '$customRelation2' => $organisation->custom_relation_label2,
            '$customInvoice1' => $organisation->custom_invoice_text_label1,
            '$customInvoice2' => $organisation->custom_invoice_text_label2,
        ];

        // Add variables for available payment types
        foreach (Gateway::$paymentTypes as $type) {
            $camelType = Gateway::getPaymentTypeName($type);
            $type = Utils::toSnakeCase($camelType);
            $variables["\${$camelType}Link"] = $invitation->getLink('payment') . "/{$type}";
            $variables["\${$camelType}Button"] = Form::emailPaymentButton($invitation->getLink('payment')  . "/{$type}");
        }
        
        $includesPasswordPlaceholder = strpos($template, '$password') !== false;
                
        $str = str_replace(array_keys($variables), array_values($variables), $template);

        if(!$includesPasswordPlaceholder && $passwordHTML){
            $pos = strrpos($str, '$password');
            if($pos !== false)
            {
                $str = substr_replace($str, $passwordHTML, $pos, 9/* length of "$password" */);
            }
        }        
        $str = str_replace('$password', '', $str);
        $str = autolink($str, 100);
        
        return $str;
    }
}
