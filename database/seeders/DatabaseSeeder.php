<?php

namespace Database\Seeders;

use App\Models\Setting;
use Database\Seeders\AccessorySeeder;
use Database\Seeders\ActionlogSeeder;
use Database\Seeders\AssetModelSeeder;
use Database\Seeders\AssetSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\ComponentSeeder;
use Database\Seeders\ConsumableSeeder;
use Database\Seeders\CustomFieldSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        // Only create default settings if they do not exist in the db.
        if (! Setting::first()) {
            // factory(Setting::class)->create();
            $this->call(SettingsSeeder::class);
        }

        $this->call(CompanySeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(LocationSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(DepreciationSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(ManufacturerSeeder::class);
        $this->call(SupplierSeeder::class);
        $this->call(AssetModelSeeder::class);
        $this->call(DepreciationSeeder::class);
        $this->call(StatuslabelSeeder::class);
        $this->call(AccessorySeeder::class);
        $this->call(SoftwareSeeder::class);
        $this->call(AssetSeeder::class);
        $this->call(LicenseSeeder::class);
        $this->call(ComponentSeeder::class);
        $this->call(ConsumableSeeder::class);
        $this->call(ActionlogSeeder::class);
        $this->call(CustomFieldSeeder::class);
        $this->call(SoftwareLicensesSeeder::class);
        $this->call(ToolSeeder::class);
        
        Artisan::call('snipeit:sync-asset-locations', ['--output' => 'all']);
        $output = Artisan::output();
        Log::info($output);

        Model::reguard();

        DB::table('imports')->truncate();
        DB::table('asset_maintenances')->truncate();
        DB::table('requested_assets')->truncate();
    }
}
