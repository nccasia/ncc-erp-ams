<?php

namespace Database\Seeders;

use App\Models\Software;
use Illuminate\Database\Seeder;

class SoftwareSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Software::truncate();
        Software::factory()->seedData()->create(['name' => 'Visual Studio', 'category_id'=> 17]);
        Software::factory()->seedData()->create(['name' => 'GearUp Booter', 'category_id'=> 17]);
        Software::factory()->seedData()->create(['name' => 'Windows 10 Pro', 'category_id'=> 18]);
        Software::factory()->seedData()->create(['name' => 'Windows 10 Pro', 'category_id'=> 18]);
        Software::factory()->seedData()->create(['name' => 'OpenVPN Client', 'category_id'=> 16]);
    }
}
