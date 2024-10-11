<?php

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory;

class ApiClientAssetsCest
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

    public function indexAssets(ApiTester $I)
    {
        $I->wantTo('Get a list of assets');

        $I->sendGET('/client-hardware');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function totalDetailAssets(ApiTester $I)
    {
        $I->wantTo('Get total detail for client asset index');

        $I->sendGET('/client-hardware/total-detail');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function totalDetailAssetsExpiration(ApiTester $I)
    {
        $I->wantTo('Get total detail for client asset index');

        $I->sendGET('/client-hardware/total-detail', ['IS_EXPIRE_PAGE' => true]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function indexAssetsExpiration(ApiTester $I)
    {
        $I->wantTo('Get a list of client assets expiration');
        // call

        $I->sendGET('/client-hardware/assetExpiration');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function createAsset(ApiTester $I, $scenario)
    {
        $I->wantTo('Create a new asset');

        $temp_asset = Asset::factory()->laptopMbp()->make([
            'asset_tag' => $this->faker->name(),
            'company_id' => Company::factory()->create()->id,
        ]);

        // setup
        $data = [
            'asset_tag' => $temp_asset->asset_tag,
            'assigned_to' => $temp_asset->assigned_to,
            'company_id' => $temp_asset->company->id,
            'model_id' => $temp_asset->model_id,
            'name' => $temp_asset->name,
            'notes' => $temp_asset->notes,
            'purchase_cost' => $temp_asset->purchase_cost,
            'purchase_date' => $temp_asset->purchase_date,
            'rtd_location_id' => $temp_asset->rtd_location_id,
            'serial' => $temp_asset->serial,
            'status_id' => $temp_asset->status_id,
            'supplier_id' => $temp_asset->supplier_id,
            'warranty_months' => $temp_asset->warranty_months,
            'customer' => $temp_asset->customer,
            'project' => $temp_asset->project,
            'isCustomerRenting' => $temp_asset->isCustomerRenting,
        ];

        // create
        $I->sendPOST('/client-hardware', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        //confirm
        $asset_created = Asset::latest()->first();
        $I->assertEquals($temp_asset->name, $asset_created->name);
        $I->assertEquals($temp_asset->model_id, $asset_created->model_id);
        $I->assertEquals($temp_asset->rtd_location_id, $asset_created->rtd_location_id);
    }

    public function updateAssetWithPatch(ApiTester $I)
    {
        $I->wantTo('Update an asset with PATCH');

        // create
        $asset = Asset::factory()->laptopMbp()->create([
            'company_id' => Company::factory()->create()->id,
            'rtd_location_id' => Location::factory()->create()->id,
        ]);

        $temp_asset = Asset::factory()->laptopAir()->make([
            'company_id' => Company::factory()->create()->id,
            'name' => $this->faker->name(),
            'rtd_location_id' => Location::factory()->create()->id,
        ]);
        $asset->image = $temp_asset->image;
        if (!$temp_asset->requestable) $temp_asset->requestable = '0';
        $asset->requestable = $temp_asset->requestable;
        $asset->save();
        $data = [
            'asset_tag' => $temp_asset->asset_tag,
            'assigned_to' => $temp_asset->assigned_to,
            'company_id' => $temp_asset->company->id,
            'model_id' => $temp_asset->model_id,
            'name' => $temp_asset->name,
            'notes' => $temp_asset->notes,
            'order_number' => $temp_asset->order_number,
            'purchase_cost' => $temp_asset->purchase_cost,
            'purchase_date' => $temp_asset->purchase_date->format('Y-m-d'),
            'rtd_location_id' => $temp_asset->rtd_location_id,
            'serial' => $temp_asset->serial,
            'status_id' => $temp_asset->status_id,
            'supplier_id' => $temp_asset->supplier_id,
            'warranty_months' => $temp_asset->warranty_months,
            'requestable' => $temp_asset->requestable,
        ];

        $I->assertNotEquals($asset->name, $data['name']);

        // update
        $I->sendPATCH('/client-hardware/' . $asset->id, $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.update.success'), $response->messages);

        //confirm
        $asset_updated = Asset::find($asset->id);
        $I->assertEquals($temp_asset->asset_tag, $asset_updated->asset_tag); // asset tag updated
        $I->assertEquals($temp_asset->name, $asset_updated->name); // asset name updated
        $I->assertEquals($temp_asset->rtd_location_id, $asset_updated->rtd_location_id); // asset rtd_location_id updated
    }

    public function deleteAssetTest(ApiTester $I)
    {
        $I->wantTo('Delete an asset');

        // create
        $asset = Asset::factory()->laptopMbp()->create();

        // delete
        $I->sendDELETE('/client-hardware/' . $asset->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.delete.success'), $response->messages);
    }

    public function assetCanCheckout(ApiTester $I)
    {
        $I->wantTo('Checkout an asset');

        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $asset = Asset::factory()->laptopMbp()->create([
            'name' => 'Test Asset Checkout',
            'company_id' => $company->id,
            'rtd_location_id' => $location->id,
            'assigned_status' => config('enum.assigned_status.DEFAULT')
        ]);
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => $location->id
        ]);

        $data = [
            "checkout_at" => Carbon::now(),
            "status_id" => config('enum.status_id.ASSIGN'),
            'assigned_user' => $user->id,
            'checkout_to_type' => 'user'
        ];

        $I->sendPost('/client-hardware/' . $asset->id . '/checkout', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.checkout.success'), $response->messages);
    }

    public function assetCanCheckin(ApiTester $I)
    {
        $I->wantTo('Checkin an asset');

        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => $location->id
        ]);
        $asset = Asset::factory()->laptopMbp()->create([
            'name' => 'Test Asset Checkin',
            'company_id' => $company->id,
            'rtd_location_id' => $location->id,
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'status_id' => config('enum.status_id.ASSIGN'),
            'assigned_to' => $user->id,
            'assigned_type' => User::class,
            'withdraw_from' => $user->id
        ]);
        $data = [
            "checkout_at" => Carbon::now(),
            "status_id" => config('enum.status_id.READY_TO_DEPLOY'),
            'assigned_user' => $user->id,
        ];

        $I->sendPost('/client-hardware/' . $asset->id . '/checkin', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.checkin.success'), $response->messages);
    }

    public function assetsCanMultipleCheckout(ApiTester $I)
    {
        $I->wantTo('Checkout multiple assets');

        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $assets = Asset::factory()->laptopMbp()->count(3)->create([
            'name' => 'Test Asset Checkout',
            'company_id' => $company->id,
            'rtd_location_id' => $location->id,
            'assigned_status' => config('enum.assigned_status.DEFAULT')
        ]);
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => $location->id
        ]);
        $data = [
            "checkout_at" => Carbon::now(),
            "status_id" => config('enum.status_id.ASSIGN'),
            'assigned_user' => $user->id,
            'checkout_to_type' => 'user',
            'assets' => $assets->pluck('id')->toArray()
        ];

        $I->sendPost('/client-hardware/checkout', $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.checkout.success'), $response->messages);
    }

    public function assetsCanMultipleCheckin(ApiTester $I)
    {
        $I->wantTo('Checkin multiple assets');

        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => $location->id
        ]);
        $assets = Asset::factory()->laptopMbp()->count(3)->create([
            'name' => 'Test Asset Checkin',
            'company_id' => $company->id,
            'rtd_location_id' => $location->id,
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'status_id' => config('enum.status_id.ASSIGN'),
            'assigned_to' => $user->id,
            'assigned_type' => User::class,
            'withdraw_from' => $user->id
        ]);
        $data = [
            "checkout_at" => Carbon::now(),
            "status_id" => config('enum.status_id.READY_TO_DEPLOY'),
            'assigned_user' => $user->id,
            'assets' => $assets->pluck('id')->toArray()
        ];

        $I->sendPost('/client-hardware/checkin', $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.checkin.success'), $response->messages);
    }

    public function assetAcceptedCheckout(ApiTester $I)
    {
        $I->wantTo('Check accepted checkout');

        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => $location->id
        ]);
        $asset = Asset::factory()->laptopMbp()->create([
            'name' => 'Test Asset Accept Checkout',
            'company_id' => $company->id,
            'rtd_location_id' => $location->id,
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'status_id' => config('enum.status_id.ASSIGN'),
            'assigned_to' => $user->id,
            'assigned_type' => User::class
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'send_accept' => $asset->id
        ];

        $I->sendPost('/client-hardware/' . $asset->id . '?_method=PUT', $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.update.success'), $response->messages);
    }

    public function assetAcceptedCheckin(ApiTester $I)
    {
        $I->wantTo('Check accepted checkin');

        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => $location->id
        ]);
        $asset = Asset::factory()->laptopMbp()->create([
            'name' => 'Test Asset Accept Checkin',
            'company_id' => $company->id,
            'rtd_location_id' => $location->id,
            'assigned_status' => config('enum.assigned_status.WAITINGCHECKIN'),
            'status_id' => config('enum.status_id.ASSIGN'),
            'assigned_to' => $user->id,
            'assigned_type' => User::class,
            'withdraw_from' => $user->id
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'send_accept' => $asset->id
        ];

        $I->sendPost('/client-hardware/' . $asset->id . '?_method=PUT', $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.update.success'), $response->messages);
    }

    public function assetAcceptedMultipleCheckin(ApiTester $I)
    {
        $I->wantTo('Check accepted multiple checkin');

        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => $location->id
        ]);
        $assets = Asset::factory()->laptopMbp()->count(3)->create([
            'company_id' => $company->id,
            'rtd_location_id' => $location->id,
            'assigned_status' => config('enum.assigned_status.WAITINGCHECKIN'),
            'status_id' => config('enum.status_id.ASSIGN'),
            'assigned_to' => $user->id,
            'assigned_type' => User::class,
            'withdraw_from' => $user->id
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'assets' => $assets->pluck('id')->toArray()
        ];

        $I->sendPost('/client-hardware?_method=PUT', $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.update.success'), $response->messages);
    }

    public function assetAcceptedMultipleCheckout(ApiTester $I)
    {
        $I->wantTo('Check accepted multiple checkin');

        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => $location->id
        ]);
        $assets = Asset::factory()->laptopMbp()->count(3)->create([
            'company_id' => $company->id,
            'rtd_location_id' => $location->id,
            'assigned_status' => config('enum.assigned_status.WAITINGCHECKOUT'),
            'status_id' => config('enum.status_id.ASSIGN'),
            'assigned_to' => $user->id,
            'assigned_type' => User::class,
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'assets' => $assets->pluck('id')->toArray()
        ];

        $I->sendPost('/client-hardware?_method=PUT', $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.update.success'), $response->messages);
    }
}
