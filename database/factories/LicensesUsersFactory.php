<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LicensesUsersFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'software_licenses_id' => $this->faker->numberBetween(1, 100),
            'assigned_to' => $this->faker->numberBetween(1, 100),
            'user_id' => 1,
            'deleted_at' => null
        ];
    }
}
