<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFinfastRequestAssetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('finfast_request_assets', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->integer('asset_id');
            $table->integer('finfast_request_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('finfast_request_assets');
    }
}
