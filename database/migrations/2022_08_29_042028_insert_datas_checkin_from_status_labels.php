<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertDatasCheckinFromStatusLabels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('status_labels')->insert([
            'id' => 6,
            'name' => 'Checkin',
            'user_id' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('status_labels')->delete([
            'id' => 6,
            'name' => 'Checkin',
            'user_id' => 1,
        ]);
    }
}
