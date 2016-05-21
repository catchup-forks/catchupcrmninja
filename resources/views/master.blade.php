<!DOCTYPE html>
<html lang="{{App::getLocale()}}">
<head>
    @if (isset($hideLogo) && $hideLogo)
        <title>{{ trans('texts.CUSTOMER_PORTAL') }}</title>
    @else
        <title>{{ isset($title) ? ($title . ' | Invoice Ninja') : ('Invoice Ninja | ' . trans('texts.app_title')) }}</title> 
        <meta name="description" content="{{ isset($description) ? $description : trans('texts.app_description') }}" />
        <link href="{{ asset('favicon-v2.png') }}" rel="shortcut icon" type="image/png">
    @endif

    <!-- Source: https://github.com/hillelcoren/invoice-ninja -->
    <!-- Version: {{ NINJA_VERSION }} -->

    <meta charset="utf-8">
    <meta property="og:site_name" content="Invoice Ninja" />
    <meta property="og:url" content="{{ SITE_URL }}" />
    <meta property="og:title" content="Invoice Ninja" />
    <meta property="og:image" content="{{ SITE_URL }}/images/round_logo.png" />
    <meta property="og:description" content="Simple, Intuitive Invoicing." />

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="msapplication-config" content="none"/> 

    <link rel="canonical" href="{{ NINJA_APP_URL }}/{{ Request::path() }}" />

    <script src="{{ asset('built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>    

    <script type="text/javascript">
        var NINJA = NINJA || {};
        NINJA.fontSize = 9;
        NINJA.isRegistered = {{ \Utils::isRegistered() ? 'true' : 'false' }};
        
        window.onerror = function (errorMsg, url, lineNumber, column, error) {
            if (errorMsg.indexOf('Script error.') > -1) {
                return;
            }

            try {
                // Use StackTraceJS to parse the error context 
                if (error) {
                    var message = error.message ? error.message : error;
                    StackTrace.fromError(error).then(function(result) {
                        var gps = new StackTraceGPS();
                        gps.findFunctionName(result[0]).then(function(result) {
                            logError(errorMsg + ': ' + JSON.stringify(result));
                        });
                    });
                } else {
                    logError(errorMsg);
                }
            } catch(err) {}

            return false;
        }

        function logError(message) {
            $.ajax({
                type: 'GET',
                url: '{{ URL::to('log_error') }}',
                data: 'error='+encodeURIComponent(message)+'&url='+encodeURIComponent(window.location)
            });
        }

        /* Set the defaults for DataTables initialisation */
        $.extend( true, $.fn.dataTable.defaults, {
            "bSortClasses": false,
            "sDom": "t<'row-fluid'<'span6'i><'span6'p>>l",
            "sPaginationType": "bootstrap",
            "bInfo": true,
            "oLanguage": {
                'sEmptyTable': "{{ trans('texts.empty_table') }}",
                'sLengthMenu': '_MENU_ {{ trans('texts.rows') }}',
                'sSearch': ''
            }
        } );
        
        /* This causes problems with some languages. ie, fr_CA
		var appLocale = '{{App::getLocale()}}';
        $.extend( true, $.fn.datepicker.defaults, {
			language: appLocale.replace("_", "-")
        });
        */

        @if (env('FACEBOOK_PIXEL'))
            <!-- Facebook Pixel Code -->
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
            document,'script','//connect.facebook.net/en_US/fbevents.js');

            fbq('init', '{{ env('FACEBOOK_PIXEL') }}');
            fbq('track', "PageView");

            (function() {
              var _fbq = window._fbq || (window._fbq = []);
              if (!_fbq.loaded) {
                var fbds = document.createElement('script');
                fbds.async = true;
                fbds.src = '//connect.facebook.net/en_US/fbds.js';
                var s = document.getElementsByTagName('script')[0];
                s.parentNode.insertBefore(fbds, s);
                _fbq.loaded = true;
             }
            })();
            
        @else
            function fbq() {
                // do nothing
            };
        @endif

        window._fbq = window._fbq || [];
            
    </script>


    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->

@yield('head')

</head>

<body class="body">

    @if (isset($_ENV['TAG_MANAGER_KEY']) && $_ENV['TAG_MANAGER_KEY'])  
    <!-- Google Tag Manager -->
    <noscript><iframe src="//www.googletagmanager.com/ns.html?id={{ $_ENV['TAG_MANAGER_KEY'] }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        '//www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $_ENV['TAG_MANAGER_KEY'] }}');</script>      
    <!-- End Google Tag Manager -->

    <script>
        function trackEvent(category, action) {}
    </script>
    @elseif (isset($_ENV['ANALYTICS_KEY']) && $_ENV['ANALYTICS_KEY'])  
    <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

        ga('create', '{{ $_ENV['ANALYTICS_KEY'] }}', 'auto');        
        ga('send', 'pageview');

        function trackEvent(category, action) {
            ga('send', 'event', category, action, this.src);
        }
    </script>
    @else
    <script>
        function trackEvent(category, action) {}
    </script>
    @endif
    
@yield('body')

<script type="text/javascript">
    NINJA.formIsChanged = {{ isset($formIsChanged) && $formIsChanged ? 'true' : 'false' }};

    $(function() {
        $('form.warn-on-exit input, form.warn-on-exit textarea, form.warn-on-exit select').change(function() {
            NINJA.formIsChanged = true;
        }); 

        @if (Session::has('trackEventCategory') && Session::has('trackEventAction'))
            trackEvent('{{ session('trackEventCategory') }}', '{{ session('trackEventAction') }}');
            @if (Session::get('trackEventAction') === '/buy_pro_plan')
                window._fbq.push(['track', '{{ env('FACEBOOK_PIXEL_BUY_PRO') }}', {'value':'{{ PRO_PLAN_PRICE }}.00','currency':'USD'}]);
            @endif
        @endif

        @if (Session::has('onReady'))
            {{ Session::get('onReady') }}
        @endif
    });
    $('form').submit(function() {
        NINJA.formIsChanged = false;
    });
    $(window).on('beforeunload', function() {
        if (NINJA.formIsChanged) {
            return "{{ trans('texts.unsaved_changes') }}";
        } else {
            return undefined;
        }
    }); 
    function openUrl(url, track) {
        trackEvent('/view_link', track ? track : url);
        window.open(url, '_blank');
    }
</script> 

</body>

</html>
