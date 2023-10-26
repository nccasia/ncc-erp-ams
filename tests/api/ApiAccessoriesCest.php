<?php

use App\Http\Transformers\AccessoriesTransformer;
use App\Models\Accessory;
use App\Models\Category;
use App\Models\Company;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\User;
use Faker\Factory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApiAccessoriesCest
{
    protected $faker;
    protected $user;
    protected $timeFormat;

    public function _before(ApiTester $I)
    {
        $this->faker = Factory::create();
        $this->user = User::factory()->create();
        $I->haveHttpHeader('Accept', 'application/json');
        $I->amBearerAuthenticated($I->getToken($this->user));
    }

    /** @test */
    public function indexAccessories(ApiTester $I)
    {
        $I->wantTo('Get a list of accessories');

        // call
        $I->sendGET('/accessories/accessories?limit=10&offset=0&order=desc');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse(), true);
        // sample verify
        $accessory = Accessory::orderByDesc('created_at')->take(10)->get()->shuffle()->first();
        $I->seeResponseContainsJson($I->removeTimestamps((new AccessoriesTransformer)->transformAccessory($accessory)));
    }

    public function totalDetailAccessories(ApiTester $I)
    {
        $I->wantTo('Get total detail of accessories');

        $filter = '?limit=10&offset=0&order=desc&sort=id'
            . '&assigned_status[0]=' . config('enum.assigned_status.DEFAULT')
            . '&status_label[0]=' . config('enum.status_id.READY_TO_DEPLOY')
            . '&purchaseDateFrom=' . Carbon::now()->subDays(5)
            . '&purchaseDateTo=' . Carbon::now()->addDays(5)
            . '&expirationDateFrom=' . Carbon::now()->subMonths(2)
            . '&expirationDateTo=' . Carbon::now()->addMonths(2)
            . '&supplier=' . Supplier::all()->random(1)->first()->id
            . '&search=' . 'Token';
        $I->sendGET('/accessories/total-detail' . $filter);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    protected function filter($I, $filterName, $value)
    {
        $I->sendGET('/accessories/accessories?limit=10&offset=0', [
            $filterName => $value,
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    protected function filterByDate($I, $from, $to, $value)
    {
        $I->sendGET('/accessories/accessories?limit=10&offset=0', [
            $from => $value[0],
            $to => $value[1],
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function filterAccessory(ApiTester $I)
    {
        //category
        $this->filter($I, 'category_id', [Category::all()->random()->first()->id]);

        //company
        $this->filter($I, 'company_id', [Company::all()->random()->first()->id]);

        //supplier
        $this->filter($I, 'supplier_id', [Supplier::all()->random()->first()->id]);

        //purchase date from -> to
        $this->filterByDate($I, 'date_from', 'date_to', ['2023-08-08', '2023-08-14']);

        //search
        $this->filter($I, 'search', 'kkk');
    }

    protected function sort($I, $column, $order)
    {
        $I->sendGET('/accessories/accessories?limit=10&offset=0', [
            'sort' => $column,
            'order' => $order,
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function sortAccessoryByColumn(ApiTester $I)
    {
        $I->wantTo("Sort By Column");

        //category
        $this->sort($I, 'category', 'asc');

        //company
        $this->sort($I, 'company', 'asc');

        //location
        $this->sort($I, 'location', 'asc');

        //manufacturer
        $this->sort($I, 'manufacturer', 'asc');

        //spullier
        $this->sort($I, 'supplier', 'desc');
    }

    /** @test */
    public function createAccessory(ApiTester $I, $scenario)
    {
        $I->wantTo('Create a new accessory');

        $temp_accessory = Accessory::factory()->appleBtKeyboard()->make([
            'name' => $this->faker->name(),
            'company_id' => Company::factory()->create()->id,
        ]);

        // setup
        $data = [
            'category_id' => $temp_accessory->category_id,
            'company_id' => $temp_accessory->company->id,
            'location_id' => $temp_accessory->location_id,
            'name' => $temp_accessory->name,
            'order_number' => $temp_accessory->order_number,
            'purchase_cost' => $temp_accessory->purchase_cost,
            'purchase_date' => $temp_accessory->purchase_date,
            'model_number' => $temp_accessory->model_number,
            'manufacturer_id' => $temp_accessory->manufacturer_id,
            'supplier_id' => $temp_accessory->supplier_id,
            'qty' => $temp_accessory->qty,
        ];

        // create success
        $I->sendPOST('/accessories/accessories', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        //error
        $data['name'] = null;
        $I->sendPOST('/accessories/accessories', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(400);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('The name field is required.', $response->messages->name[0]);
        $I->assertEquals('error', $response->status);
        $I->assertNull($response->payload);
    }

    // Put is routed to the same method in the controller
    // DO we actually need to test both?

    // /** @test */
    public function updateAccessoryWithPatch(ApiTester $I, $scenario)
    {
        $I->wantTo('Update an accessory with PATCH');

        // create
        $accessory = Accessory::factory()->appleBtKeyboard()->create([
            'name' => $this->faker->name(),
            'company_id' => Company::factory()->create()->id,
            'location_id' => Location::factory()->create()->id,
        ]);
        $I->assertInstanceOf(Accessory::class, $accessory);

        $temp_accessory = Accessory::factory()->microsoftMouse()->make([
            'company_id' => Company::factory()->create()->id,
            'name' => $this->faker->name(),
            'location_id' => Location::factory()->create()->id,
            'image' => $accessory->image,
        ]);
        $data = [
            'category_id' => $temp_accessory->category_id,
            'company_id' => $temp_accessory->company->id,
            'location_id' => $temp_accessory->location_id,
            'name' => $temp_accessory->name,
            'order_number' => $temp_accessory->order_number,
            'purchase_cost' => $temp_accessory->purchase_cost,
            'purchase_date' => $temp_accessory->purchase_date,
            'model_number' => $temp_accessory->model_number,
            'manufacturer_id' => $temp_accessory->manufacturer_id,
            'supplier_id' => $temp_accessory->supplier_id,
            'qty' => $temp_accessory->qty,
        ];

        $I->assertNotEquals($accessory->name, $data['name']);

        // update success
        $I->sendPATCH('/accessories/accessories/' . $accessory->id, $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());

        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/accessories/message.update.success'), $response->messages);
        $I->assertEquals($accessory->id, $response->payload->id); // accessory id does not change
        $I->assertEquals($temp_accessory->company_id, $response->payload->company_id); // company_id updated
        $I->assertEquals($temp_accessory->name, $response->payload->name); // accessory name updated
        $I->assertEquals($temp_accessory->location_id, $response->payload->location_id); // accessory location_id updated
        $temp_accessory->created_at = $response->payload->created_at;
        $temp_accessory->updated_at = $response->payload->updated_at;
        $temp_accessory->id = $accessory->id;
        // verify
        $I->sendGET('/accessories/accessories/' . $accessory->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson((new AccessoriesTransformer)->transformAccessory($temp_accessory));
    }

    /** @test */
    public function deleteAccessoryTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Delete an accessory');

        // create
        $accessory = Accessory::factory()->appleBtKeyboard()->create([
            'name' => 'Soon to be deleted',
        ]);
        $I->assertInstanceOf(Accessory::class, $accessory);

        // delete
        $I->sendDELETE('/accessories/accessories/' . $accessory->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/accessories/message.delete.success'), $response->messages);
        $I->assertNull($response->payload);
    }

    public function checkoutAccessory(ApiTester $I)
    {
        $I->wantTo('checkout an accessory');
        $accessory = Accessory::factory()->appleUsbKeyboard()->create([
            'name' => $this->faker->name(),
            'company_id' => Company::factory()->create()->id,
            'location_id' => Location::factory()->create()->id,
        ]);
        $user = User::factory()->create();

        //checkout success
        $I->sendGET("/accessories/{$accessory->id}/checkout", [
            'assigned_to' => $user->id,
            'category_id' => 'Keyboardss',
            'name' => $accessory->name,
            'note' => '',
        ]);

        $response = json_decode($I->grabResponse());
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->assertEquals($accessory->name, $response->payload->accessory);
        $I->assertEquals(trans('admin/accessories/message.checkout.success'), $response->messages);
    }

    public function selectlistAccessories(ApiTester $I)
    {
        $I->wantTo('get a list of accessories');

        $I->sendGET('/accessories/selectlist', [
            'search' => 'h',
        ]);

        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function checkinAccessories(ApiTester $I)
    {
        $accessory = Accessory::factory()->appleUsbKeyboard()->create([
            'name' => $this->faker->name(),
            'company_id' => Company::factory()->create()->id,
            'location_id' => Location::factory()->create()->id,
        ]);

        $user = User::factory()->create();

        $accessory_user = DB::table('accessories_users')->insertGetId([
            'user_id' => $this->user->id,
            'accessory_id' => $accessory->id,
            'assigned_to' => $user->id,
        ]);

        //response null
        $id_null = $accessory_user + 1;
        $I->sendGET("/accessories/{$id_null}/checkin");
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals(trans('admin/accessories/message.does_not_exist'), $response->messages);

        //success
        $I->sendGET("/accessories/{$accessory_user}/checkin");
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/accessories/message.checkin.success'), $response->messages);
        $I->assertEquals($accessory->name, $response->payload->accessory);
    }
}
