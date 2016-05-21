<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHeaderFooterOption extends Migration
{

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    /*
    Schema::table('gateways', function ($table) {
      $table->boolean('is_offsite');
      $table->boolean('is_secure');
    });
    */
/*
    Schema::table('expenses', function ($table) {
      $table->string('transaction_id')->nullable()->change();
      $table->unsignedInteger('bank_id')->nullable()->change();
    });
*/
    Schema::table('vendors', function ($table) {
      $table->string('transaction_name')->nullable();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    /*
    Schema::table('gateways', function ($table) {
      $table->dropColumn('is_offsite');
      $table->dropColumn('is_secure');
    });
    */
  }

}
