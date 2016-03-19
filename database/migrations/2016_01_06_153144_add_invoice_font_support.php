<?php

use Illuminate\Database\Migrations\Migration;

class AddInvoiceFontSupport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fonts', function ($t) {
            $t->increments('id');

            $t->string('name');
            $t->string('folder');
            $t->string('css_stack');
            $t->smallInteger('css_weight')->default(400);
            $t->string('google_font');
            $t->string('normal');
            $t->string('bold');
            $t->string('italics');
            $t->string('bolditalics');
            $t->boolean('is_early_access');
            $t->unsignedInteger('sort_order')->default(10000);
        });

        // Create fonts
        //$seeder = new FontsSeeder();
        //$seeder->run();



        /*
        Schema::table('organisations', function ($table) {
              $table->foreign('header_font_id')->references('id')->on('fonts');
            $table->foreign('body_font_id')->references('id')->on('fonts');
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
        Schema::dropIfExists('fonts');
    }
}
