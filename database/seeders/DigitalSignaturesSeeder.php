<?php

namespace Database\Seeders;

use App\Models\DigitalSignatures;
use Illuminate\Database\Seeder;

class DigitalSignaturesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DigitalSignatures::truncate();
        DigitalSignatures::factory()->count(100)->create();
    }
}
