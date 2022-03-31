<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFinfastSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('finfast_settings', function (Blueprint $table) {
            $table->string("f_key");
            $table->text("f_value");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('finfast_settings', function (Blueprint $table) {
            if (Schema::hasColumn('finfast_settings', 'f_key')) {
                $table->dropColumn('f_key');
            }
            if (Schema::hasColumn('finfast_settings', 'f_value')) {
                $table->dropColumn('f_value');
            }
        });
    }
}
