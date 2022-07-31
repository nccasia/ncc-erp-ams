<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MigrateCheckoutData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
           $sql1 = "INSERT INTO asset_histories (created_at, updated_at, type)
            SELECT '2022-6-1', '2022-6-1', 0 
            FROM assets a 
            where EXISTS (
                SELECT 1 
                from status_labels  s
                where a.status_id = s.id and s.name = 'ASSIGN'
            );";


            $sql2 = "INSERT INTO asset_history_details (asset_id , created_at, updated_at)
            SELECT a.id , a.created_at , a.updated_at  
            FROM asset_histories a";
            
            DB::insert($sql1);
            DB::insert($sql2);
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
