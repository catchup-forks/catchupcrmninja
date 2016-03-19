<?php
use Illuminate\Database\Migrations\Migration;

class SetupOrganisationsTables extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {   
        Schema::dropIfExists('organisation_gateways');
        Schema::dropIfExists('organisations');
        Schema::dropIfExists('gateways');
        
        Schema::create('organisations', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('timezone_id')->nullable();
            $t->unsignedInteger('date_format_id')->nullable();
            $t->unsignedInteger('datetime_format_id')->nullable();
            $t->unsignedInteger('currency_id')->nullable();

            $t->string('name')->nullable();
            $t->string('ip');
            $t->string('organisation_key')->unique();
            $t->timestamp('last_login')->nullable();

            $t->string('address1')->nullable();
            $t->string('housenumber')->nullable();

            $t->string('postal_code')->nullable();

            $t->string('city')->nullable();
            $t->string('state')->nullable();

            $t->unsignedInteger('country_id')->nullable();

            $t->string('work_phone')->nullable();
            $t->string('work_email')->nullable();

            $t->text('invoice_terms')->nullable();
            $t->text('email_footer')->nullable();
            $t->unsignedInteger('industry_id')->nullable();
            $t->unsignedInteger('size_id')->nullable();

            $t->boolean('invoice_taxes')->default(true);
            $t->boolean('invoice_item_taxes')->default(false);


            $t->date('pro_plan_paid')->nullable();



            $t->string('custom_label1')->nullable();
            $t->string('custom_value1')->nullable();

            $t->string('custom_label2')->nullable();
            $t->string('custom_value2')->nullable();

            $t->string('custom_relation_label1')->nullable();
            $t->string('custom_relation_label2')->nullable();


            $t->boolean('fill_products')->default(true);
            $t->boolean('update_products')->default(true);


            $t->string('primary_color')->nullable();
            $t->string('secondary_color')->nullable();

            $t->boolean('hide_quantity')->default(0);
            $t->boolean('hide_paid_to_date')->default(0);

            $t->string('custom_invoice_label1')->nullable();
            $t->string('custom_invoice_label2')->nullable();

            $t->boolean('custom_invoice_taxes1')->nullable();
            $t->boolean('custom_invoice_taxes2')->nullable();


            $t->string('vat_number')->nullable();

            $t->text('email_template_invoice')->nullable();
            $t->text('email_template_quote')->nullable();
            $t->text('email_template_payment')->nullable();


            $t->string('invoice_number_prefix')->nullable();
            $t->integer('invoice_number_counter')->default(1)->nullable();

            $t->string('quote_number_prefix')->nullable();
            $t->integer('quote_number_counter')->default(1)->nullable();

            $t->boolean('share_counter')->default(true);

            $t->string('id_number')->nullable();

            $t->smallInteger('token_billing_type_id')->default(TOKEN_BILLING_ALWAYS);

            $t->text('invoice_footer')->nullable();

            $t->smallInteger('pdf_email_attachment')->default(0);

            $t->smallInteger('font_size')->default(DEFAULT_FONT_SIZE);

            $t->text('invoice_labels')->nullable();

            $t->unsignedInteger('header_font_id')->default(1);
            $t->unsignedInteger('body_font_id')->default(1);

            $t->unsignedInteger('default_tax_rate_id')->nullable();
            $t->smallInteger('recurring_hour')->default(DEFAULT_SEND_RECURRING_HOUR);

            $t->string('invoice_number_pattern')->nullable();
            $t->string('quote_number_pattern')->nullable();

            $t->smallInteger('email_design_id')->default(1);
            $t->boolean('enable_email_markup')->default(false);
            $t->string('website')->nullable();

            $t->text('relation_view_css')->nullable();

            $t->boolean('auto_convert_quote')->default(true);

            $t->boolean('all_pages_footer');
            $t->boolean('all_pages_header');
            $t->boolean('show_currency_code');
            $t->date('pro_plan_trial')->nullable();

            $t->boolean('enable_portal_password')->default(0);
            $t->boolean('send_portal_password')->default(0);

            $t->string('custom_invoice_item_label1')->nullable();
            $t->string('custom_invoice_item_label2')->nullable();
            $t->string('recurring_invoice_number_prefix')->default('R');
            $t->boolean('enable_relation_portal')->default(true);
            $t->text('invoice_fields')->nullable();
            $t->text('devices')->nullable();

            $t->foreign('timezone_id')->references('id')->on('timezones');
            $t->foreign('date_format_id')->references('id')->on('date_formats');
            $t->foreign('datetime_format_id')->references('id')->on('datetime_formats');
            $t->foreign('country_id')->references('id')->on('countries');
            $t->foreign('currency_id')->references('id')->on('currencies');
            $t->foreign('industry_id')->references('id')->on('industries');
            $t->foreign('size_id')->references('id')->on('sizes');


            $t->timestamps();
            $t->softDeletes();
        });


        DB::table('organisations')->update(['fill_products' => true]);
        DB::table('organisations')->update(['update_products' => true]);


        // set initial counter value for organisations with invoices
        $organisations = DB::table('organisations')->lists('id');

        foreach ($organisations as $organisationId) {

            $invoiceNumbers = DB::table('invoices')->where('organisation_id', $organisationId)->lists('invoice_number');
            $max = 0;

            foreach ($invoiceNumbers as $invoiceNumber) {
                $number = intval(preg_replace('/[^0-9]/', '', $invoiceNumber));
                $max = max($max, $number);
            }

            DB::table('organisations')->where('id', $organisationId)->update(['invoice_number_counter' => ++$max]);
        }

        Schema::table('organisations', function($table)
        {
            $table->mediumText('custom_design')->nullable();
        });


        Schema::table('organisations', function ($table) {
            $table->string('iframe_url')->nullable();
            $table->boolean('military_time')->default(false);
            $table->unsignedInteger('referral_user_id')->nullable();
        });

        Schema::table('organisations', function ($table) {
            $table->string('custom_invoice_text_label1')->nullable();
            $table->string('custom_invoice_text_label2')->nullable();
        });

        Schema::table('organisations', function($table)
        {
            $table->text('quote_terms')->nullable();
        });

        $organisations = DB::table('organisations')
            ->orderBy('id')
            ->get(['id', 'invoice_terms']);

        foreach ($organisations as $organisation) {
            DB::table('organisations')
                ->where('id', $organisation->id)
                ->update(['quote_terms' => $organisation->invoice_terms]);
        }





        DB::table('invoice_designs')->insert(['id' => CUSTOM_DESIGN, 'name' => 'Custom']);


        Schema::create('gateways', function($t)
        {
            $t->increments('id');


            $t->string('name');
            $t->string('provider');
            $t->boolean('visible')->default(true);

            $t->timestamps();
        });

        Schema::create('organisation_gateways', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('organisation_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('gateway_id');

            $t->text('config');

            $t->boolean('show_address')->default(true)->nullable();
            $t->boolean('update_address')->default(true)->nullable();


            $t->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');
            $t->foreign('gateway_id')->references('id')->on('gateways');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $t->unsignedInteger('public_id')->index();
            $t->unique( array('organisation_id','public_id') );

            $t->timestamps();
            $t->softDeletes();
        });

        $gateways = DB::table('organisation_gateways')
            ->get(['id', 'config']);
        foreach ($gateways as $gateway) {
            DB::table('organisation_gateways')
                ->where('id', $gateway->id)
                ->update(['config' => Crypt::encrypt($gateway->config)]);
        }


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //$table->dropColumn('show_address');
        //$table->dropColumn('update_address');
        //$table->dropColumn('pro_plan_paid');

        Schema::dropIfExists('organisation_gateways');
        Schema::dropIfExists('organisations');
        Schema::dropIfExists('gateways');


        $gateways = DB::table('organisation_gateways')
            ->get(['id', 'config']);
        foreach ($gateways as $gateway) {
            DB::table('organisation_gateways')
                ->where('id', $gateway->id)
                ->update(['config' => Crypt::decrypt($gateway->config)]);
        }

        /*
        if (Schema::hasColumn('organisations', 'header_font_id')) {
            Schema::table('organisations', function ($table) {
                //$table->dropForeign('accounts_header_font_id_foreign');
                $table->dropColumn('header_font_id');
            });
        }

        if (Schema::hasColumn('organisations', 'body_font_id')) {
            Schema::table('organisations', function ($table) {
                //$table->dropForeign('accounts_body_font_id_foreign');
                $table->dropColumn('body_font_id');
            });
        }
        */


    }
}
