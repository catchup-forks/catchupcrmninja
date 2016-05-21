<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProductsSettings extends Migration
{

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::table('organisations', function ($table) {
      $table->dropColumn('fill_products');
      $table->dropColumn('update_products');
    });
  }

}
