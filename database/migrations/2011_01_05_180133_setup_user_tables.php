<?php
use Illuminate\Database\Migrations\Migration;

class SetupUserTables extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('users');

        Schema::create('users', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('organisation_id')->index();

            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('phone')->nullable();
            $t->string('username')->unique();
            $t->string('email')->nullable();
            $t->string('password');
            $t->string('confirmation_code')->nullable();
            $t->boolean('registered')->default(false);
            $t->boolean('confirmed')->default(false);
            $t->integer('theme_id')->nullable();

            $t->boolean('notify_sent')->default(true);
            $t->boolean('notify_viewed')->default(false);
            $t->boolean('notify_paid')->default(true);

            $t->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');

            $t->unsignedInteger('public_id')->nullable();
            $t->unique(array('organisation_id', 'public_id'));


            $t->unsignedInteger('news_feed_id')->nullable();

            $t->timestamps();
            $t->softDeletes();
        });

        Schema::table('users', function ($table) {
            $table->smallInteger('failed_logins')->nullable();
        });


        Schema::table('users', function ($table) {
            $table->boolean('force_pdfjs')->default(false);
        });


        Schema::table('users', function ($table) {
            $table->string('remember_token', 100)->nullable();
        });

        Schema::table('users', function ($table) {
            $table->boolean('notify_approved')->default(true);
        });


    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
