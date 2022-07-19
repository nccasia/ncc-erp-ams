<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateColumnNameTableStatusLabels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('status_labels')->where(['name' => 'Pending'])->update(['name' => 'Check Lại - Bảo Hành']);
        DB::table('status_labels')->where(['name' => 'Ready to Deploy'])->update(['name' => 'Trong Kho']);
        DB::table('status_labels')->where(['name' => 'Broken'])->update(['name' => 'Hỏng']);
        DB::table('status_labels')->where(['name' => 'Assign'])->update(['name' => 'Bàn Giao']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('status_labels')->where(['name' => 'Check Lại - Bảo Hành'])->update(['name' => 'Pending']);
        DB::table('status_labels')->where(['name' => 'Trong Kho'])->update(['name' => 'Ready to Deploy']);
        DB::table('status_labels')->where(['name' => 'Hỏng'])->update(['name' => 'Broken']);
        DB::table('status_labels')->where(['name' => 'Bàn Giao'])->update(['name' => 'Assign']);
    }
}
