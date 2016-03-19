<script type="text/javascript">

    var currencies = {!! \Cache::get('currencies') !!};
    var currencyMap = {};
    for (var i=0; i<currencies.length; i++) {
        var currency = currencies[i];
        currencyMap[currency.id] = currency;
    }

    var countries = {!! \Cache::get('countries') !!};
    var countryMap = {};
    for (var i=0; i<countries.length; i++) {
        var country = countries[i];
        countryMap[country.id] = country;
    }

    var NINJA = NINJA || {};
    @if (Auth::check())
    NINJA.primaryColor = "{{ Auth::user()->organisation->primary_color }}";
    NINJA.secondaryColor = "{{ Auth::user()->organisation->secondary_color }}";
    NINJA.fontSize = {{ Auth::user()->organisation->font_size ?: DEFAULT_FONT_SIZE }};
    NINJA.headerFont = {!! json_encode(Auth::user()->organisation->getHeaderFontName()) !!};
    NINJA.bodyFont = {!! json_encode(Auth::user()->organisation->getBodyFontName()) !!};
    @else
    NINJA.fontSize = {{ DEFAULT_FONT_SIZE }};
    @endif

    NINJA.parseFloat = function(str) {
        if (!str) return '';
        str = (str+'').replace(/[^0-9\.\-]/g, '');
        
        return window.parseFloat(str);
    }

    function formatMoneyInvoice(value, invoice, hideSymbol) {
        var organisation = invoice.organisation;
        var relation = invoice.relation;

        return formatMoneyOrganisation(value, organisation, relation, hideSymbol);
    }

    function formatMoneyOrganisation(value, organisation, relation, hideSymbol) {
        var currencyId = false;
        var countryId = false;

        if (relation && relation.currency_id) {
            currencyId = relation.currency_id;
        } else if (organisation && organisation.currency_id) {
            currencyId = organisation.currency_id;
        }

        if (relation && relation.country_id) {
            countryId = relation.country_id;
        } else if (organisation && organisation.country_id) {
            countryId = organisation.country_id;
        }

        return formatMoney(value, currencyId, countryId, hideSymbol)
    }

    function formatMoney(value, currencyId, countryId, hideSymbol) {
        value = NINJA.parseFloat(value);

        if (!currencyId) {
            currencyId = {{ Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY) }};
        }

        var currency = currencyMap[currencyId];
        var thousand = currency.thousand_separator;
        var decimal = currency.decimal_separator;
        var code = currency.code;
        var swapSymbol = false;

        if (countryId && currencyId == {{ CURRENCY_EURO }}) {
            var country = countryMap[countryId];
            swapSymbol = country.swap_currency_symbol;
            if (country.thousand_separator) {
                thousand = country.thousand_separator;
            }
            if (country.decimal_separator) {
                decimal = country.decimal_separator;
            }
        }

        value = accounting.formatMoney(value, '', 2, thousand, decimal);
        var symbol = currency.symbol;

        if (hideSymbol) {
            return value;
        } else if (!symbol) {
            return value + ' ' + code;
        } else if (swapSymbol) {
            return value + ' ' + symbol.trim();
        } else {
            return symbol + value;
        }
    }

</script>