<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSoftwareLicensesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('software_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('software_id');
            $table->string('licenses');
            $table->integer('seats');
            $table->date('purchase_date');
            $table->date('expiration_date');
            $table->decimal('purchase_cost', 20, 2);
            $table->integer('user_id');
            $table->integer('checkout_count')->default(0);
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
        Schema::dropIfExists('software_licenses');
    }
}
