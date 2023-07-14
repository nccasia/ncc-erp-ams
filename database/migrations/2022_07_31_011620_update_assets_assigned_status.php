<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateAssetsAssignedStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $accept_status = config('enum.assigned_status.ACCEPT');
        $sql = "
            UPDATE assets as a
            SET assigned_status = $accept_status
            WHERE EXISTS
                (SELECT 1
                FROM status_labels s
                WHERE a.status_id = s.id
                AND s.name = 'ASSIGN' )
        ";
        DB::update($sql);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
