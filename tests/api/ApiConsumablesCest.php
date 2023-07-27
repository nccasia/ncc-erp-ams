<?php

use App\Http\Transformers\ConsumablesTransformer;
use App\Models\Category;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;

class ApiConsumablesCest
{
    protected $user;
    protected $timeFormat;

    public function _before(ApiTester $I)
    {
        $this->user = User::factory()->create();
        $I->haveHttpHeader('Accept', 'application/json');
        $I->amBearerAuthenticated($I->getToken($this->user));
    }

    /** @test */
    public function indexConsumables(ApiTester $I)
    {
        $I->wantTo('Get a list of consumables');

        // call
        $I->sendGET('/consumables?limit=10');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse(), true);

        // sample verify
        $consumable = Consumable::orderByDesc('created_at')->take(10)->get()->shuffle()->first();

        $I->seeResponseContainsJson($I->removeTimestamps((new ConsumablesTransformer)->transformConsumable($consumable)));
    }

    /** @test */
    public function createConsumable(ApiTester $I, $scenario)
    {
        $I->wantTo('Create a new consumable');
        $category = Category::factory()->create(['category_type' => 'accessory']);
        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $temp_consumable = Consumable::factory()->ink()->make([
            'name' => 'Test Consumable Name',
            'company_id' => $company->id,
            'location_id' => $location->id,
            'category_id' => $category->id
        ]);

        // setup
        $data = [
            'category_id' => $temp_consumable->category_id,
            'company_id' => $temp_consumable->company->id,
            'location_id' => $temp_consumable->location_id,
            'manufacturer_id' => $temp_consumable->manufacturer_id,
            'name' => $temp_consumable->name,
            'order_number' => $temp_consumable->order_number,
            'purchase_cost' => $temp_consumable->purchase_cost,
            'purchase_date' => $temp_consumable->purchase_date,
            'qty' => $temp_consumable->qty,
            'model_number' => $temp_consumable->model_number,
        ];

        // create
        $I->sendPOST('/consumables', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    /** @test */
    public function updateConsumableWithPatch(ApiTester $I, $scenario)
    {
        $I->wantTo('Update an consumable with PATCH');

        // create
        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $consumable = Consumable::factory()->ink()->create([
            'name' => 'Original Consumable Name',
            'company_id' => $company->id,
            'location_id' => $location->id,
        ]);
        $I->assertInstanceOf(Consumable::class, $consumable);

        $category = Category::factory()->create(['category_type' => 'accessory']);
        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $temp_consumable = Consumable::factory()->cardstock()->make([
            'name' => 'Test Consumable Name',
            'company_id' => $company->id,
            'location_id' => $location->id,
            'category_id' => $category->id
        ]);

        $data = [
            'category_id' => $temp_consumable->category_id,
            'company_id' => $temp_consumable->company->id,
            'item_no' => $temp_consumable->item_no,
            'location_id' => $temp_consumable->location_id,
            'name' => $temp_consumable->name,
            'order_number' => $temp_consumable->order_number,
            'purchase_cost' => $temp_consumable->purchase_cost,
            'purchase_date' => $temp_consumable->purchase_date,
            'model_number' => $temp_consumable->model_number,
            'manufacturer_id' => $temp_consumable->manufacturer_id,
            'supplier_id' => $temp_consumable->supplier_id,
            'qty' => $temp_consumable->qty,
        ];

        $I->assertNotEquals($consumable->name, $data['name']);

        // update
        $I->sendPATCH('/consumables/'.$consumable->id, $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());

        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/consumables/message.update.success'), $response->messages);
        $I->assertEquals($consumable->id, $response->payload->id); // consumable id does not change
        $I->assertEquals($temp_consumable->company_id, $response->payload->company_id); // company_id updated
        $I->assertEquals($temp_consumable->name, $response->payload->name); // consumable name updated
        $I->assertEquals($temp_consumable->location_id, $response->payload->location_id); // consumable location_id updated
        $temp_consumable->created_at = Carbon::parse($response->payload->created_at);
        $temp_consumable->updated_at = Carbon::parse($response->payload->updated_at);
        $temp_consumable->id = $consumable->id;
        // verify
        $I->sendGET('/consumables/'.$consumable->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson((new ConsumablesTransformer)->transformConsumable($temp_consumable));
    }

    /** @test */
    public function deleteConsumableTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Delete an consumable');

        // create
        $consumable = Consumable::factory()->ink()->create([
            'name' => 'Soon to be deleted',
        ]);
        $I->assertInstanceOf(Consumable::class, $consumable);

        // delete
        $I->sendDELETE('/consumables/'.$consumable->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/consumables/message.delete.success'), $response->messages);

        // verify, expect a 200
        $I->sendGET('/consumables/'.$consumable->id);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }
}
