<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SoftwareLicensesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'software_id' => $this->faker->numberBetween(2, 4),
            'licenses' => $this->faker->uuid,
            'seats'   =>  $this->faker->numberBetween(1, 50),
            'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get()),
            'expiration_date' => $this->faker->dateTimeBetween('now', '+3 years', date_default_timezone_get())->format('Y-m-d H:i:s'),
            'purchase_cost' => $this->faker->randomFloat(2, 100, 500),
            'user_id' => 1,
            'created_at' => $this->faker->dateTime(),
            'updated_at' => $this->faker->dateTime(),
            'deleted_at' => null,
            'checkout_count' => 0,
        ];
    }
}
