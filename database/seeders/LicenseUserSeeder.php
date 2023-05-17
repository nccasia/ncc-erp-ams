<?php

namespace Database\Seeders;

use App\Models\LicensesUsers;
use Illuminate\Database\Seeder;

class LicenseUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        LicensesUsers::truncate();
        LicensesUsers::factory(100)->create();
    }
}
