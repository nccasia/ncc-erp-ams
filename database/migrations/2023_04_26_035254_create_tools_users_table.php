<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateToolsUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tools_users', function (Blueprint $table) {
            $table->id();
            $table->integer('tool_id');
            $table->integer('assigned_to');
            $table->text('notes')->nullable();
            $table->datetime('checkout_at')->nullable();
            $table->datetime('checkin_at')->nullable();
            $table->softDeletes();
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
        Schema::dropIfExists('tools_users');
    }
}
