<?php
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('first_name')->default('');
            $table->string('last_name')->default('');
            $table->string('email')->default('');
            $table->string('password')->default('');
            $table->string('address')->default('');
            $table->integer('age')->default(0);
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('users');
    }
}