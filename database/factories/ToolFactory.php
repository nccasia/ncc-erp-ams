<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Tool;

use Illuminate\Database\Eloquent\Factories\Factory;

class ToolFactory extends Factory
{

    protected $model = Tool::class;

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
            'purchase_cost' => $this->faker->randomFloat(2, 100, 500),
            'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get()),
            'version' => $this->faker->unixTime('now').'',
            'notes'   => 'Created by DB seeder',
            'category_id' => Category::where('category_type', '=', 'tool')->inRandomOrder()->first()->id,
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

    public function timeSheet()
    {
        return $this->state(function () {
            return [
                'name'=>'Time Sheet',
                'category_id' => 17,
                'notes' => $this->faker->sentence,
            ];
        });
    }
    
}
