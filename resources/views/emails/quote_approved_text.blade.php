{!! trans('texts.email_salutation', ['name' => $userName]) !!}

{!! trans("texts.notification_{$entityType}_approved", ['amount' => $invoiceAmount, 'relation' => $relationName, 'invoice' => $invoiceNumber]) !!}

{!! trans('texts.email_signature') !!}
{!! trans('texts.email_from') !!}

{!! trans('texts.user_email_footer') !!}