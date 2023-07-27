<?php

use App\Http\Transformers\DigitalSignaturesTransformer;
use App\Models\Category;
use App\Models\DigitalSignatures;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Faker\Factory;
use Illuminate\Support\Facades\DB;

class ApiDigitalSignaturesCest
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
    }

    // tests
    public function indexDigitalSingatures(ApiTester $I)
    {
        $I->wantTo('Get a list of digital signatures');

        // call
        $I->sendGET('/digital_signatures?limit=10&offset=0&order=desc&sort=id');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function createDigitalSignature(ApiTester $I, $scenario)
    {
        $I->wantTo('Create a new digital signature');
        $category = Category::factory()->create(['category_type' => 'accessory']);
        $location = Location::factory()->create();
        // setup
        $data = [
            'name' => $this->faker->name(),
            'seri' =>  $this->faker->uuid,
            'supplier_id' => Supplier::all()->random()->id,
            'user_id' => 1,
            'assigned_status' => 0,
            'assigned_to' => null,
            'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get())->format('Y-m-d H:i:s'),
            'expiration_date' => $this->faker->dateTimeBetween('now', 'now', date_default_timezone_get())->format('Y-m-d H:i:s'),
            'purchase_cost' => $this->faker->randomFloat(2, '299.99', '2999.99'),
            'note'   => 'Created by DB seeder',
            'status_id' => 5,
            'category_id' => $category->id,
            'qty' => $this->faker->numberBetween(5, 10),
            'location_id' => $location->id,
            'warranty_months' => $this->faker->numberBetween(5, 10)
        ];

        // create
        $I->sendPOST('/digital_signatures', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function updateDigitalSignatureWithPatch(ApiTester $I, $scenario)
    {
        $I->wantTo('Update a digital signature with PATCH');

        // create
        $digital_signature = DigitalSignatures::factory()->create([
            'qty' => 2
        ]);
        $I->assertInstanceOf(DigitalSignatures::class, $digital_signature);
        $category = Category::factory()->create(['category_type' => 'accessory']);
        $location = Location::factory()->create();
        $temp = DigitalSignatures::factory()->create([
            'name' => 'Test update',
            'qty' => $this->faker->numberBetween(5, 10),
            'location_id' => $location->id,
            'category_id' => $category->id
        ]);

        $data = [
            'name' => $temp->name,
            'supplier_id' => $temp->supplier_id,
            'user_id' => $temp->user_id,
            'assigned_status' => $temp->assigned_status,
            'assigned_to' => $temp->assigned_to,
            'purchase_date' => $temp->purchase_date->format('Y-m-d H:i:s'),
            'expiration_date' => $temp->expiration_date->format('Y-m-d H:i:s'),
            'purchase_cost' => $temp->purchase_cost,
            'note'   => $temp->note,
            'status_id' => $temp->status_id,
            'category_id' => $temp->category_id,
            'qty' => $temp->qty,
            'location_id' => $temp->location_id,
            'warranty_months' => $temp->warranty_months
        ];

        $I->assertNotEquals($digital_signature->name, $data['name']);

        // update
        $I->sendPut('/digital_signatures/' . $digital_signature->id, $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());

        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/digital_signatures/message.update.success'), $response->messages);
        $I->assertEquals($digital_signature->id, $response->payload->id);
        $I->assertEquals($temp->name, $response->payload->name);
        $temp->id = $digital_signature->id;
        $temp->seri = $digital_signature->seri;

        // verify
        $I->sendGET('/digital_signatures/' . $digital_signature->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson((new DigitalSignaturesTransformer)->transformSignature($temp));
    }

    public function deleteAssetTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Delete a digital singature');

        // create
        $digital_signature = DigitalSignatures::factory()->create();
        $I->assertInstanceOf(DigitalSignatures::class, $digital_signature);

        // delete
        $I->sendDELETE('/digital_signatures/' . $digital_signature->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/digital_signatures/message.delete.success'), $response->messages);

        // verify, expect a 200
        $I->sendGET('/digital_signatures/' . $digital_signature->id);
        $I->seeResponseCodeIs(404);
    }
}
