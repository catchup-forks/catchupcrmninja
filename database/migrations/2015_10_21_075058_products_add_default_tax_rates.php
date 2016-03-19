<?php

use Illuminate\Database\Migrations\Migration;

class ProductsAddDefaultTaxRates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function ($table) {
            $table->unsignedInteger('default_tax_rate_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function ($table) {
            $table->dropColumn('default_tax_rate_id');
        });
    }
}
