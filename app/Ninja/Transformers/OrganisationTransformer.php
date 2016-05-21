<?php namespace App\Ninja\Transformers;

use App\Models\Organisation;
use App\Models\OrganisationToken;
use App\Models\Contact;
use App\Models\Product;
use App\Models\TaxRate;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class OrganisationTransformer extends EntityTransformer
{
    protected $defaultIncludes = [
        'users',
        'products',
        'taxRates',
        'payments'
    ];

    protected $availableIncludes = [
        'relations',
        'invoices',
    ];

    public function includeUsers(Organisation $organisation)
    {
        $transformer = new UserTransformer($organisation, $this->serializer);
        return $this->includeCollection($organisation->users, $transformer, 'users');
    }

    public function includeRelations(Organisation $organisation)
    {
        $transformer = new RelationTransformer($organisation, $this->serializer);
        return $this->includeCollection($organisation->relations, $transformer, 'relations');
    }

    public function includeInvoices(Organisation $organisation)
    {
        $transformer = new InvoiceTransformer($organisation, $this->serializer);
        return $this->includeCollection($organisation->invoices, $transformer, 'invoices');
    }

    public function includeProducts(Organisation $organisation)
    {
        $transformer = new ProductTransformer($organisation, $this->serializer);
        return $this->includeCollection($organisation->products, $transformer, 'products');
    }

    public function includeTaxRates(Organisation $organisation)
    {
        $transformer = new TaxRateTransformer($organisation, $this->serializer);
        return $this->includeCollection($organisation->tax_rates, $transformer, 'taxRates');
    }

    public function includePayments(Organisation $organisation)
    {
        $transformer = new PaymentTransformer($organisation, $this->serializer);
        return $this->includeCollection($organisation->payments, $transformer, 'payments');
    }

    public function transform(Organisation $organisation)
    {
        return [
            'organisation_key' => $organisation->organisation_key,
            'name' => $organisation->present()->name,
            'currency_id' => (int) $organisation->currency_id,
            'timezone_id' => (int) $organisation->timezone_id,
            'date_format_id' => (int) $organisation->date_format_id,
            'datetime_format_id' => (int) $organisation->datetime_format_id,
            'updated_at' => $this->getTimestamp($organisation->updated_at),
            'archived_at' => $this->getTimestamp($organisation->deleted_at),
            'address1' => $organisation->address1,
            'housenumber' => $organisation->housenumber,
            'city' => $organisation->city,
            'state' => $organisation->state,
            'postal_code' => $organisation->postal_code,
            'country_id' => (int) $organisation->country_id,
            'invoice_terms' => $organisation->invoice_terms,
            'email_footer' => $organisation->email_footer,
            'industry_id' => (int) $organisation->industry_id,
            'size_id' => (int) $organisation->size_id,
            'invoice_taxes' => (bool) $organisation->invoice_taxes,
            'invoice_item_taxes' => (bool) $organisation->invoice_item_taxes,
            'invoice_design_id' => (int) $organisation->invoice_design_id,
            'client_view_css' => (string) $organisation->client_view_css,
            'work_phone' => $organisation->work_phone,
            'work_email' => $organisation->work_email,
            'language_id' => (int) $organisation->language_id,
            'fill_products' => (bool) $organisation->fill_products,
            'update_products' => (bool) $organisation->update_products,
            'vat_number' => $organisation->vat_number,
            'custom_invoice_label1' => $organisation->custom_invoice_label1,
            'custom_invoice_label2' => $organisation->custom_invoice_label2,
            'custom_invoice_taxes1' => $organisation->custom_invoice_taxes1,
            'custom_invoice_taxes2' => $organisation->custom_invoice_taxes1,
            'custom_label1' => $organisation->custom_label1,
            'custom_label2' => $organisation->custom_label2,
            'custom_value1' => $organisation->custom_value1,
            'custom_value2' => $organisation->custom_value2
        ];
    }
}