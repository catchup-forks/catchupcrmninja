@extends('emails.master')

@section('markup')
    @if ($organisation->enable_email_markup)
        @include('emails.partials.relation_view_action')
    @endif
@stop

@section('content')
    <tr>
        <td bgcolor="#F4F5F5" style="border-collapse: collapse;">&nbsp;</td>
    </tr>
    <tr>
        <td style="border-collapse: collapse;">
            <table cellpadding="10" cellspacing="0" border="0" bgcolor="{{ $organisation->primary_color ?: '#2E2B2B' }}" width="580" align="center" class="header"
                style="border-bottom-width: 6px; border-bottom-color: {{ $organisation->primary_color ?: '#2E2B2B' }}; border-bottom-style: solid;">
                <tr>
                    <td class="logo" width="205" style="border-collapse: collapse; vertical-align: middle; line-height: 16px;" valign="middle">
                        @include('emails.partials.organisation_logo')
                    </td>
                    <td width="183" style="border-collapse: collapse; vertical-align: middle; line-height: 16px;" valign="middle">
                        <p class="left" style="line-height: 22px; margin: 3px 0 0; padding: 0;">
                            @if ($invoice->due_date)
                                <span style="font-size: 11px; color: #8f8d8e;">
                                    {{ strtoupper(trans('texts.due_by', ['date' => $organisation->formatDate($invoice->due_date)])) }}
                                </span><br />
                            @endif
                            <span style="font-size: 19px; color: #FFFFFF;">
                                {{ trans("texts.{$entityType}") }} {{ $invoice->invoice_number }}
                            </span>
                        </p>
                    </td>
                    <td style="border-collapse: collapse; vertical-align: middle; line-height: 16px;" valign="middle">
                        <p style="margin: 0; padding: 0;">
                            <span style="font-size: 12px; color: #8f8d8e;">
                                {{ strtoupper(trans('texts.' . $invoice->present()->balanceDueLabel)) }}:
                            </span><br />
                            <span class="total" style="font-size: 27px; color: #FFFFFF; margin-top: 5px;display: block;">
                                {{ $organisation->formatMoney($invoice->getRequestedAmount(), $relation) }}
                            </span>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="content" style="border-collapse: collapse;">
            <div style="font-size: 18px; margin: 42px 40px 42px; padding: 0; max-width: 520px;">{!! $body !!}</div>
        </td>
    </tr>
@stop

@section('footer')
    <p style="color: #A7A6A6; font-size: 13px; line-height: 18px; margin: 0 0 7px; padding: 0;">
        {{ $organisation->address1 }}
        @if ($organisation->address1 && $organisation->getCityState())
            -
        @endif
        {{ $organisation->getCityState() }}
        @if ($organisation->address1 || $organisation->getCityState())
            <br />
        @endif

        @if ($organisation->website)
            <strong><a href="{{ $organisation->present()->website }}" style="color: #A7A6A6; text-decoration: none; font-weight: bold; font-size: 10px;">{{ $organisation->website }}</a></strong>
        @endif
    </p>
@stop