<?php

namespace Database\Seeders;

use App\Models\SoftwareLicenses;
use Illuminate\Database\Seeder;

class SoftwareLicensesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        SoftwareLicenses::truncate();
        SoftwareLicenses::factory(100)->create();
    }
}
