<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomInvoiceFields extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::table('invoice_items', function ($table) {
      $table->string('custom_value1')->nullable();
      $table->string('custom_value2')->nullable();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::table('invoice_items', function ($table) {
      $table->dropColumn('custom_value1');
      $table->dropColumn('custom_value2');
    });
  }
}
