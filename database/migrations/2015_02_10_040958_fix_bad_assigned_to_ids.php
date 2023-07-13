<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class FixBadAssignedToIds extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        DB::update('update '.DB::getTablePrefix().'assets SET assigned_to=NULL where assigned_to=0');

        Schema::table('status_labels', function ($table) {
            $table->boolean('deployable')->default(0);
            $table->boolean('pending')->default(0);
            $table->boolean('archived')->default(0);
            $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('status_labels', function ($table) {
            $table->dropColumn('deployable');
            $table->dropColumn('pending');
            $table->dropColumn('archived');
            $table->dropColumn('notes');
        });
    }
}
