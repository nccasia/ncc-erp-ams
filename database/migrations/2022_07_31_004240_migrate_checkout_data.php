<?php

use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\AssetHistoryDetail;
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
        $assets = Asset::all();
        foreach ($assets as $asset) {
            $assetHistory = AssetHistory::create([
                "assigned_to" => $asset->assigned_to,
                "type" => 0,
                "created_at" => "2022-6-1",
                "updated_at" => "2022-6-1"
            ]);

            if ($assetHistory) {
                AssetHistoryDetail::create([
                    "asset_histories_id" => $assetHistory->id,
                    "asset_id" => $asset->id,
                    "created_at" => "2022-6-1",
                    "updated_at" => "2022-6-1"
                ]);
            }
        }
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
