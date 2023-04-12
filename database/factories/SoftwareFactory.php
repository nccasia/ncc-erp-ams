<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Software;
use Illuminate\Database\Eloquent\Factories\Factory;

class SoftwareFactory extends Factory
{

    protected $model = Software::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => 1,
            'name' => $this->faker->name,
            'version' => $this->faker->unixTime('now'),
            'software_tag' => $this->faker->unixTime('now').'',
            'notes'   => 'Created by DB seeder',
            'category_id' => Category::where('category_type', '=', 'software')->inRandomOrder()->first()->id,
            'manufacturer_id' => $this->faker->numberBetween(1, 11),
            'deleted_at' => null,
        ];
    }

    public function seedData()
    {
        return $this->state(function () {
            return [
                'notes' => $this->faker->sentence,
            ];
        });
    }
    
}
