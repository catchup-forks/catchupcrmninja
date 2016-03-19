<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDefaultQuoteTerms extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::table('organisations', function($table)
        {
            $table->text('quote_terms')->nullable();
        });

        $accounts = DB::table('organisations')
                        ->orderBy('id')
                        ->get(['id', 'invoice_terms']);

        foreach ($accounts as $organisation) {
            DB::table('organisations')
                ->where('id', $organisation->id)
                ->update(['quote_terms' => $organisation->invoice_terms]);
        }
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
            $table->dropColumn('quote_terms');
        });
	}

}
