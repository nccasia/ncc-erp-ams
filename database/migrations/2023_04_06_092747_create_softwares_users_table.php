<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSoftwaresUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('softwares_users', function (Blueprint $table) {
            $table->id();
            $table->integer('software_id');
            $table->integer('assigned_to');
            $table->integer('assigned_status');
            $table->integer('status_id');
            $table->string('assigned_type');
            $table->date('last_checkout');
            $table->integer('location_id');
            $table->integer('department_id');
            $table->integer('withdraw_from');
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
        Schema::dropIfExists('softwares_users');
    }
}
