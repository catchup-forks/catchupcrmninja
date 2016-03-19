@if ($organisation->hasLogo())
    @if ($organisation->website)
        <a href="{{ $organisation->website }}" style="color: #19BB40; text-decoration: underline;">
    @endif

    <img src="{{ $message->embed($organisation->getLogoFullPath()) }}" style="max-height:50px; max-width:140px; margin-left: 33px;" />

    @if ($organisation->website)
        </a>
    @endif
@endif
