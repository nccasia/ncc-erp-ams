<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Supplier;
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
            'name' => $this->faker->name(),
            'supplier_id' => Supplier::all()->random()->id,
            'user_id' => 1,
            'assigned_status' => 0,
            'assigned_to' => null,
            'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get()),
            'expiration_date' => $this->faker->dateTimeBetween('now', date_default_timezone_get()),
            'purchase_cost' => $this->faker->randomFloat(2, '299.99', '2999.99'),
            'notes'   => 'Created by DB seeder',
            'status_id' => 5,
            'category_id' => Category::where('category_type', '=', 'tool')->inRandomOrder()->first()->id,
            'qty' => $this->faker->numberBetween(5, 10),
            'location_id' => 1,
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
