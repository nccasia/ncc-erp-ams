<?php

namespace Database\Seeders;

use App\Models\Tool;
use Illuminate\Database\Seeder;

class ToolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Tool::truncate();
        Tool::factory()->count(1)->seedData()->create(['name' => 'TimeSheets', 'category_id'=> 19]);
        Tool::factory()->count(1)->seedData()->create(['name' => 'IMS', 'category_id'=> 19]);
        Tool::factory()->count(1)->seedData()->create(['name' => 'Azure', 'category_id'=> 19]);
        Tool::factory()->count(1)->seedData()->create(['name' => 'AMS', 'category_id'=> 19]);
    }
}
