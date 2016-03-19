@extends('header')

@section('content')


<center>
    @if (!session(SESSION_USER_ORGANISATIONS) || count(session(SESSION_USER_ORGANISATIONS)) < 5)
        {!! Button::success(trans('texts.add_company'))->asLinkTo('/login?new_company=true') !!}
    @endif
</center>
<p>&nbsp;</p>

<div class="row">
    <div class="col-md-6 col-md-offset-3">
    </div>
</div>

<div class="row">
    <div class="col-md-6 col-md-offset-3">
        <div class="panel panel-default">
            <div class="panel-body">
            <table class="table table-striped">
            @foreach (Session::get(SESSION_USER_ORGANISATIONS) as $organisation)
                <tr>
                    <td>
                    @if (isset($organisation->logo_path))
                        {!! HTML::image($organisation->logo_path.'?no_cache='.time(), 'Logo', ['width' => 100]) !!}
                    @endif
                    </td>                    
                    <td>
                        <h3>{{ $organisation->account_name }}<br/>
                        <small>{{ $organisation->user_name }}
                            @if ($organisation->user_id == Auth::user()->id)
                            | {{ trans('texts.current_user')}}
                            @endif
                        </small></h3>
                    </td>
                    <td>{!! Button::primary(trans('texts.unlink'))->withAttributes(['onclick'=>"return showUnlink({$organisation->id}, {$organisation->user_id})"]) !!}</td>
                </tr>
            @endforeach
            </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="unlinkModal" tabindex="-1" role="dialog" aria-labelledby="unlinkModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title" id="myModalLabel">{{ trans('texts.unlink_organisation') }}</h4>
      </div>

      <div class="container">        
        <h3>{{ trans('texts.are_you_sure') }}</h3>        
      </div>

      <div class="modal-footer" id="signUpFooter">          
        <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
        <button type="button" class="btn btn-primary" onclick="unlinkOrganisation()">{{ trans('texts.unlink') }}</button>
      </div>
    </div>
  </div>
</div>


    <script type="text/javascript">
      function showUnlink(userOrganisationId, userId) {
        NINJA.unlink = {
            'userOrganisationId': userOrganisationId,
            'userId': userId
        };
        $('#unlinkModal').modal('show');    
        return false;
      }

      function unlinkOrganisation() {
        window.location = '{{ URL::to('/unlink_organisation') }}' + '/' + NINJA.unlink.userOrganisationId + '/' + NINJA.unlink.userId;
      }

    </script>

@stop