<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TaxRatesAddSupportThreeDecimalTaxes extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('tax_rates', function($table) {
			$table->decimal('rate', 13, 3)->change();
		});
	}
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('tax_rates', function($table) {
			$table->decimal('rate', 13, 2)->change();
		});
	}
}