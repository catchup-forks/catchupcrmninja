<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProductsSettings extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('organisations', function($table)
		{
			$table->boolean('fill_products')->default(true);
			$table->boolean('update_products')->default(true);
		});		

		DB::table('organisations')->update(['fill_products' => true]);
		DB::table('organisations')->update(['update_products' => true]);
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
			$table->dropColumn('fill_products');
			$table->dropColumn('update_products');			
		});
	}

}
