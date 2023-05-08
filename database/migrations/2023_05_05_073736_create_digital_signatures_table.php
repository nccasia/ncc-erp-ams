<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDigitalSignaturesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('digital_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('seri');
            $table->integer('supplier_id');
            $table->integer('user_id');
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 20, 2);
            $table->date('expiration_date');
            $table->integer('status_id')->default(1);
            $table->integer('assigned_to')->nullable();
            $table->integer('assigned_status')->nullable();
            $table->datetime('checkout_date')->nullable();
            $table->datetime('last_checkout')->nullable();
            $table->datetime('checkin_date')->nullable();
            $table->string('assigned_type')->nullable();
            $table->integer('withdraw_from')->nullable();
            $table->integer('checkin_counter')->default(0);
            $table->integer('checkout_counter')->default(0);
            $table->string('note')->nullable();
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
        Schema::dropIfExists('digital_signatures');
    }
}
