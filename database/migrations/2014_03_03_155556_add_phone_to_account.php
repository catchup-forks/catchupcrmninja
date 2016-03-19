<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPhoneToOrganisation extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('organisations', function($table)
		{
			$table->string('work_phone')->nullable();
			$table->string('work_email')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('organisations', function($table)
		{
			$table->dropColumn('work_phone');
			$table->dropColumn('work_email');
		});
	}
	
}