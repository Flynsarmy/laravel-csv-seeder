<?php

use Illuminate\Database\Migrations\Migration;

class CreateSecondTestsUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('csvSeederTest2')->create('tests_users2', function ($table) {
            $table->increments('id');
            $table->string('first_name')->default('');
            $table->string('last_name')->default('');
            $table->string('email')->default('');
            $table->string('password')->default('');
            $table->string('address')->default('');
            $table->integer('age')->default(0);
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('csvSeederTest2')->drop('tests_users2');
    }
}
