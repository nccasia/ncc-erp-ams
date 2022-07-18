<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDataStatusLabelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('status_labels')->where(['id' => 1])->update(['name' => 'Check Lại - Bảo Hành']);
        DB::table('status_labels')->where(['id' => 2])->update(['name' => 'Trong Kho']);
        DB::table('status_labels')->where(['id' => 3])->update(['name' => 'Hỏng']);
        DB::table('status_labels')->where(['id' => 4])->update(['name' => 'Bàn Giao']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('status_labels')->where(['id' => 1])->update(['name' => 'Pending']);
        DB::table('status_labels')->where(['id' => 2])->update(['name' => 'Ready to Deploy']);
        DB::table('status_labels')->where(['id' => 3])->update(['name' => 'Broken']);
        DB::table('status_labels')->where(['id' => 4])->update(['name' => 'Assign']);
    }
}
