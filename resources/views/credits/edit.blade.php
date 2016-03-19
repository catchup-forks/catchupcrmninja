@extends('header')

@section('content')

	
	{!! Former::open($url)->addClass('col-md-10 col-md-offset-1 warn-on-exit')->method($method)->rules(array(
			'relation' => 'required',
  		'amount' => 'required',		
	)) !!}
	
	<div class="row">
        <div class="col-md-10 col-md-offset-1">

            <div class="panel panel-default">
            <div class="panel-body">

			{!! Former::select('relation')->addOption('', '')->addGroupClass('relation-select') !!}
			{!! Former::text('amount') !!}
			{!! Former::text('credit_date')
                        ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))
                        ->addGroupClass('credit_date')
                        ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
			{!! Former::textarea('private_notes') !!}

            </div>
            </div>

        </div>
    </div>


	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/credits'))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

	{!! Former::close() !!}

	<script type="text/javascript">

	
	var relations = {!! $relations !!};

	$(function() {

		var $relationSelect = $('select#relation');
		for (var i=0; i<relations.length; i++) {
			var relation = relations[i];
			$relationSelect.append(new Option(getRelationDisplayName(relation), relation.public_id));
		}	

		if ({{ $relationPublicId ? 'true' : 'false' }}) {
			$relationSelect.val({{ $relationPublicId }});
		}

		$relationSelect.combobox();
		
		$('#currency_id').combobox();
		$('#credit_date').datepicker('update', new Date());

        @if (!$relationPublicId)
            $('.relation-select input.form-control').focus();
        @else
            $('#amount').focus();
        @endif

        $('.credit_date .input-group-addon').click(function() {
            toggleDatePicker('credit_date');
        });
	});

	</script>

@stop