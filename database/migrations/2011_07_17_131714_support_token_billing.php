<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SupportTokenBilling extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::create('account_gateway_tokens', function($table)
        {
            $table->increments('id');
            $table->unsignedInteger('organisation_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('account_gateway_id');
            $table->unsignedInteger('relation_id');
            $table->string('token');

            $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('account_gateway_id')->references('id')->on('organisation_gateways')->onDelete('cascade');
            $table->foreign('relation_id')->references('id')->on('relations')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('organisations')->update(['token_billing_type_id' => TOKEN_BILLING_ALWAYS]);
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::drop('account_gateway_tokens');
	}

}