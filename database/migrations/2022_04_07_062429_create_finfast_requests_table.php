<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFinfastRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('finfast_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('name');
            $table->string('note')->nullable();
            $table->integer('branch_id');
            $table->integer('supplier_id');
            $table->integer('entry_id');
            $table->enum('status', array_values(config('enum.request_status')))->default(config('enum.request_status.PENDING'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('finfast_requests');
    }
}
