<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Tool;
use App\Models\User;
use Faker\Factory;

class ApiToolCest
{
    protected $faker;
    protected $user;
    protected $timeFormat;
    public function _before(ApiTester $I)
    {
        $this->faker = Factory::create();
        $this->user = User::factory()->create();
        Setting::getSettings()->time_display_format = 'H:i';
        $I->amBearerAuthenticated($I->getToken($this->user));
        $I->haveHttpHeader('Content-Type', 'application/json');
    }

    // tests
    public function indexTools(ApiTester $I)
    {
        $I->wantTo('Get a list of tools');
        // call
        $I->sendGET('/tools?limit=10&offset=0&order=desc');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function createTool(ApiTester $I)
    {
        $I->wantTo('Create a new Tool');
        
        //set up data
        $data =  [
            'name' => $this->faker->name(),
            'supplier_id' => Supplier::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'assigned_status' => 0,
            'assigned_to' => null,
            'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get())->format('Y-m-d H:i:s'),
            'expiration_date' => $this->faker->dateTimeBetween('+1 days', '+30 years' ,date_default_timezone_get())->format('Y-m-d H:i:s'),
            'purchase_cost' => $this->faker->randomFloat(2, '299.99', '2999.99'),
            'notes'   => 'Created by DB seeder',
            'status_id' => 5,
            'category_id' => Category::where('category_type', '=', 'tool')->inRandomOrder()->first()->id,
            'qty' => $this->faker->numberBetween(5, 10),
            'location_id' => Location::factory()->create()->id,
        ];

        //send request
        $I->sendPOST('/tools', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }
    public function updateToolWithPut(ApiTester $I)
    {
        $I->wantTo('Update a tool with PUT');

        // create
        $tool = Tool::factory()->create([
            'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get())->format('Y-m-d H:i:s'),
            'expiration_date' => $this->faker->dateTimeBetween('+1 days', '+30 years' ,date_default_timezone_get())->format('Y-m-d H:i:s'),
        ]);
        $I->assertInstanceOf(Tool::class, $tool);

        $data = [
            'name' => 'Updated Tool name',
            'supplier_id' => Supplier::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'assigned_status' => 0,
            'assigned_to' => null,
            'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get())->format('Y-m-d H:i:s'),
            'expiration_date' => $this->faker->dateTimeBetween('+1 days', '+30 years' ,date_default_timezone_get())->format('Y-m-d H:i:s'),
            'purchase_cost' => $this->faker->randomFloat(2, '299.99', '2999.99'),
            'notes'   => 'Created by DB seeder',
            'status_id' => 5,
            'category_id' => Category::where('category_type', '=', 'tool')->inRandomOrder()->first()->id,
            'qty' => $this->faker->numberBetween(5, 10),
            'location_id' => Location::factory()->create()->id,
        ];
        

        $response = $I->sendPut('/tools/'.$tool->id, $data);

        $response = json_decode($response);
        $I->assertEquals('Updated Tool name', $response->payload->name);
        $I->assertEquals($tool->id, $response->payload->id);
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

    }
    public function deleteTool(ApiTester $I)
    {
        $I->wantTo('Delete a tool');
        // create
        $tool = Tool::factory()->create([
            'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get())->format('Y-m-d H:i:s'),
            'expiration_date' => $this->faker->dateTimeBetween('+1 days', '+30 years' ,date_default_timezone_get())->format('Y-m-d H:i:s'),
        ]);
        $I->assertInstanceOf(Tool::class, $tool);

        $I->sendDELETE('/tools/'.$tool->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/tools/message.delete.success'), $response->messages);
    }
}
