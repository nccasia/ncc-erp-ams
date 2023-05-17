<?php

namespace Database\Seeders;

use App\Models\Statuslabel;
use Illuminate\Database\Seeder;

class StatuslabelSeeder extends Seeder
{
    public function run()
    {
        Statuslabel::truncate();
        Statuslabel::factory()->pending()->create();
        Statuslabel::factory()->broken()->create();
        Statuslabel::factory()->assign()->create();
        Statuslabel::factory()->readyToDeploy()->create();
    }
}
