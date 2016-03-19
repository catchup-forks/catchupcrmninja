@extends('header')

@section('content')
	@parent

    @include('organisations.nav', ['selected' => ORGANISATION_IMPORT_EXPORT])

	{!! Former::open('/import_csv')->addClass('warn-on-exit') !!}

    @if (isset($data[ENTITY_CLIENT]))
        @include('organisations.partials.map', $data[ENTITY_CLIENT])
    @endif

    @if (isset($data[ENTITY_INVOICE]))
        @include('organisations.partials.map', $data[ENTITY_INVOICE])
    @endif

    {!! Former::actions( 
        Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/settings/import_export'))->appendIcon(Icon::create('remove-circle')),
        Button::success(trans('texts.import'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))) !!}
    
    {!! Former::close() !!}

@stop