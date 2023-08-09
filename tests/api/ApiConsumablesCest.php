<?php

use App\Http\Transformers\ConsumablesTransformer;
use App\Models\Category;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use Faker\Factory;

class ApiConsumablesCest
{
    protected $user;
    protected $timeFormat;
    protected $faker;

    public function _before(ApiTester $I)
    {
        $this->faker = Factory::create();
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

    protected function sort($I, $column, $order)
    {
        $I->sendGET('/consumables?limit=10&offset=0', [
            'sort' => $column,
            'order' => $order,
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function sortConsubaleByColumn(ApiTester $I)
    {
        $I->wantTo('Search Consumables By Column');
        //category
        $this->sort($I, 'category', 'desc');

        //location
        $this->sort($I, 'location', 'asc');
    }

    
    protected function filter($I, $filterName, $value)
    {
        $I->sendGET('/consumables?limit=10&offset=0', [
            $filterName => $value,
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    protected function filterByDate($I, $from, $to, $value)
    {
        $I->sendGET('/consumables?limit=10&offset=0', [
            $from => $value[0],
            $to => $value[1],
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function filterConsumable(ApiTester $I)
    {
        //search
        $this->filter($I, 'search', 'k');

        //purchase date from -> to
        $this->filterByDate($I, 'date_from', 'date_to', ['2023-08-08', '2023-08-14']);

        //category
        $this->filter($I, 'category_id', [Category::all()->random()->first()->id]);
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

        //error
        $data['name'] = null;
        $I->sendPOST('/consumables', $data);
        $I->seeResponseCodeIs(400);
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
    }

    public function selectlistConsumables(ApiTester $I)
    {
        $I->wantTo('get a list of consumables');
        
        $I->sendGET('/consumables/selectlist', [
            'search' => 'h',
        ]);

        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function checkoutConsumables(ApiTester $I)
    {
        $I->wantTo("checkout a consumable");
        $consumable = Consumable::factory()->ink()->create();
        $id_error = $consumable->id + 1;
        $user = User::factory()->create();

        //error not exist
        $I->sendGET("consumables/{$id_error}/checkout");
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals(trans('admin/consumables/message.does_not_exist'), $response->messages);

        //success
        $I->sendGET("consumables/{$consumable->id}/checkout", [
            'assigned_to' => $user->id,
            'name' => $consumable->name,
            'note' => $this->faker->text(),
            'category_id' => Category::select('name')->where('id', '=', $consumable->category_id)->first(),
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/consumables/message.checkout.success'), $response->messages);
    }

    public function getDataView(ApiTester $I)
    {
        $I->wantTo("get Data view of consumable");
        //$user = User::factory()->create();
        $consumable = Consumable::factory()->ink()->create();

        $I->sendGET("consumables/view/{$consumable->id}/users");
        $response = $I->grabResponse();
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->assertStringContainsString("total", $response);
    }
}
