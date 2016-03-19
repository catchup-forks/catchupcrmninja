<tr>
    <td>{{ trans('texts.relation') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.first_name') }}</td>
    <td>{{ trans('texts.last_name') }}</td>
    <td>{{ trans('texts.email') }}</td>
    <td>{{ trans('texts.phone') }}</td>
</tr>

@foreach ($contacts as $contact)
    @if (!$contact->relation->is_deleted)
        <tr>
            <td>{{ $contact->relation->getDisplayName() }}</td>
            @if ($multiUser)
                <td>{{ $contact->user->getDisplayName() }}</td>
            @endif
            <td>{{ $contact->first_name }}</td>
            <td>{{ $contact->last_name }}</td>
            <td>{{ $contact->email }}</td>
            <td>{{ $contact->phone }}</td>
        </tr>
    @endif
@endforeach

<tr><td></td></tr>