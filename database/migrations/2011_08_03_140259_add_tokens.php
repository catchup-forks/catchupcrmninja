<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTokens extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::create('account_tokens', function($table)
        {
            $table->increments('id');
            $table->unsignedInteger('organisation_id')->index();
            $table->unsignedInteger('user_id');

            $table->string('name')->nullable();
            $table->string('token')->unique();

            $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->unsignedInteger('public_id')->nullable();
            $table->unique(['organisation_id', 'public_id']);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('activities', function($table)
        {
            $table->unsignedInteger('token_id')->nullable();
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::drop('account_tokens');

        Schema::table('activities', function($table)
        {
            $table->dropColumn('token_id');
        });
	}

}
