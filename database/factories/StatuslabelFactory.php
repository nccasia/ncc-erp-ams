<?php

namespace Database\Factories;

use App\Models\Statuslabel;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatuslabelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Statuslabel::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name'      => $this->faker->sentence,
            'created_at' => $this->faker->dateTime(),
            'updated_at' => $this->faker->dateTime(),
            'user_id' => 1,
            'deleted_at' => null,
            'deployable' => 0,
            'pending' => 0,
            'archived' => 0,
            'notes' => '',
        ];
    }

    public function pending()
    {
        return $this->state(function () {
            return [
                'id' => 1,
                'name' => "Pending",
                'notes' => $this->faker->sentence,
                'pending' => 1,
                'default_label' => 1,
            ];
        });
    }

    public function broken()
    {
        return $this->state(function () {
            return [
                'id' => 3,
                'name' => "Broken",
                'notes' => $this->faker->sentence,
                'default_label' => 0,
            ];
        });
    }

    public function assign()
    {
        return $this->state(function () {
            return [
                'id' => 4,
                'name' => "Assign",
                'notes' => $this->faker->sentence,
                'deployable' => 1,
                'default_label' => 0,
            ];
        });
    }

    public function readyToDeploy()
    {
        return $this->state(function () {
            return [
                'id' => 5,
                'name' => "Ready to Deploy",
                'notes' => $this->faker->sentence,
                'deployable' => 1,
                'default_label' => 1,
            ];
        });
    }
}
