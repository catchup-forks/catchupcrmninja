<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAcceptedCreditCardsToOrganisationGateways extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('organisation_gateways', function($table)
		{
			$table->unsignedInteger('accepted_credit_cards')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('organisation_gateways', function($table)
		{
			$table->dropColumn('accepted_credit_cards');
		});
	}

}