<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSourceCurrencyToExpenses extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    /*
    Schema::table('expenses', function (Blueprint $table) {
      $table->dropColumn('foreign_amount');
      $table->unsignedInteger('currency_id')->nullable(false)->change();
      $table->renameColumn('currency_id', 'invoice_currency_id');
      $table->unsignedInteger('expense_currency_id');
    });
    */
/*
    Schema::table('expenses', function (Blueprint $table) {
      // set organisation value so we're able to create foreign constraint
      DB::statement('update expenses e
                            left join organisations a on a.id = e.organisation_id
                            set e.expense_currency_id = COALESCE(a.currency_id, 1)');
      $table->foreign('invoice_currency_id')->references('id')->on('currencies');
      $table->foreign('expense_currency_id')->references('id')->on('currencies');
    });
*/
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    /*
    Schema::table('expenses', function ($table) {
    });
    */
  }
}
