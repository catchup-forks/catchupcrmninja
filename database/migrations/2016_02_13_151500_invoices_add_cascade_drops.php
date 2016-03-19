<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InvoicesAddCascaseDrops extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('invoices', function($table)
		{
	    $table->dropForeign('invoices_organisation_id_foreign');
	    $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{

	}

}
