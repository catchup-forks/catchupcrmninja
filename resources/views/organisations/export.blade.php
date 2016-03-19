@extends('header')

@section('content')
	@parent
    @include('organisations.nav', ['selected' => ORGANISATION_IMPORT_EXPORT])

	{{ Former::open()->addClass('col-md-9 col-md-offset-1') }}
	{{ Former::legend('Export Relation Data') }}
	{{ Button::lg_primary_submit('Download') }}
	{{ Former::close() }}

@stop