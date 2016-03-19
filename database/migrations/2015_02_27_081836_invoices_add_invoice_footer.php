<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InvoicesAddInvoiceFooter extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::table('invoices', function($table)
        {
            $table->text('invoice_footer')->nullable();
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::table('invoices', function($table)
        {
            $table->dropColumn('invoice_footer');
        });
	}

}
