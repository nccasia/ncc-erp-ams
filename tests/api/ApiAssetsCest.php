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

class ApiAssetsCest
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

    protected function getFilterForIndex()
    {
        $filter = '?limit=20&sort=id&order=desc'
            . '&assigned_status[0]=' . config('enum.assigned_status.DEFAULT')
            . '&status_label[0]=' . config('enum.status_id.READY_TO_DEPLOY')
            . '&category[0]=' . Category::where('category_type', '=', 'asset')->first()->id
            . '&dateFrom=' . Carbon::now()
            . '&dateTo=' . Carbon::now()->addDays(5)
            . '&supplier_id=' . Supplier::all()->random(1)->first()->id
            . '&location_id=' . Location::all()->random(1)->first()->id
            . '&manufacturer_id=' . Manufacturer::all()->random(1)->first()->id
            . '&company_id=' . Company::all()->random(1)->first()->id
            . '&rtd_location_id=' . Location::all()->random(1)->first()->id
            . '&model_id=' . AssetModel::all()->random(1)->first()->id
            . '&assigned_to=' . User::all()->random(1)->first()->id
            . '&assigned_type=' . 'App/Models/User'
            . '&customer=' . urlencode('C 3')
            . '&project=' . urlencode('P 6')
            . '&isCustomerRenting=' . (rand(0, 1))
            . '&categoryName=' . 'Desktops';
        return $filter;
    }

    /** @test */
    public function indexAssets(ApiTester $I)
    {
        $I->wantTo('Get a list of assets');

        $filter = $this->getFilterForIndex();

        $I->sendGET('/hardware' . $filter);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function totalDetailAssets(ApiTester $I)
    {
        $I->wantTo('Get total detail for assets index');

        $filter = $this->getFilterForIndex();

        $I->sendGET('/hardware/total-detail' . $filter);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function indexAssetsExpiration(ApiTester $I)
    {
        $I->wantTo('Get a list of assets expiration');
        // call
        $filter = $this->getFilterForIndex();

        $I->sendGET('/hardware/assetExpiration' . $filter);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function indexAssetAssigned(ApiTester $I)
    {
        $I->wantTo('Get a list of assigned assets');

        $filter = $this->getFilterForIndex();
        
        $I->sendGET('/hardware/assign' . $filter);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function indexAssetRequestable(ApiTester $I)
    {
        $I->wantTo('Get a list of requestable assets');

        $filter = $this->getFilterForIndex();
        
        $I->sendGET('account/requestable/hardware' . $filter);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function getAssetByTag(ApiTester $I)
    {
        $I->wantTo('Get an asset by asset_tag');

        $asset = $asset = Asset::factory()->laptopMbp()->make([
            'asset_tag' => $this->faker->name(),
            'company_id' => Company::factory()->create()->id,
        ]);

        $I->sendGet('/hardware/bytag/'.$asset->asset_tag);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }

    public function getAssetBySerial(ApiTester $I)
    {
        $I->wantTo('Get an asset by asset_tag');

        $asset = $asset = Asset::factory()->laptopMbp()->make([
            'asset_tag' => $this->faker->name(),
            'company_id' => Company::factory()->create()->id,
        ]);

        $I->sendGet('/hardware/byserial/'.$asset->serial);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }

    /** @test */
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
        $I->sendPOST('/hardware', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    /** @test */
    public function updateAssetWithPatch(ApiTester $I, $scenario)
    {
        $I->wantTo('Update an asset with PATCH');

        // create
        $asset = Asset::factory()->laptopMbp()->create([
            'company_id' => Company::factory()->create()->id,
            'rtd_location_id' => Location::factory()->create()->id,
        ]);
        $I->assertInstanceOf(Asset::class, $asset);

        $temp_asset = Asset::factory()->laptopAir()->make([
            'company_id' => Company::factory()->create()->id,
            'name' => $this->faker->name(),
            'rtd_location_id' => Location::factory()->create()->id,
        ]);
        $asset->image = $temp_asset->image;
        if(!$temp_asset->requestable) $temp_asset->requestable = '0';
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
            'customer' => $temp_asset->customer,
            'project' => $temp_asset->project,
            'isCustomerRenting' => $temp_asset->isCustomerRenting,
        ];

        $I->assertNotEquals($asset->name, $data['name']);

        // update
        $I->sendPATCH('/hardware/'.$asset->id, $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.update.success'), $response->messages);
        $I->assertEquals($asset->id, $response->payload->id); // asset id does not change
        $I->assertEquals($temp_asset->asset_tag, $response->payload->asset_tag); // asset tag updated
        $I->assertEquals($temp_asset->name, $response->payload->name); // asset name updated
        $I->assertEquals($temp_asset->rtd_location_id, $response->payload->rtd_location_id); // asset rtd_location_id updated
        $temp_asset->created_at = Carbon::parse($response->payload->created_at);
        $temp_asset->updated_at = Carbon::parse($response->payload->updated_at);
        $temp_asset->id = $asset->id;
        $temp_asset->location_id = $response->payload->rtd_location_id;

        // verify
        $I->sendGET('/hardware/'.$asset->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    /** @test */
    public function deleteAssetTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Delete an asset');

        // create
        $asset = Asset::factory()->laptopMbp()->create();
        $I->assertInstanceOf(Asset::class, $asset);

        // delete
        $I->sendDELETE('/hardware/'.$asset->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.delete.success'), $response->messages);

        // verify, expect a 200
        $I->sendGET('/hardware/'.$asset->id);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        // Make sure we're soft deleted.
        $response = json_decode($I->grabResponse());
        $I->assertNotNull($response->deleted_at);
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

        $I->sendPost('/hardware/'.$asset->id.'/checkout',$data);
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
            'assigned_type' => 'App\Models\User',
            'withdraw_from' => $user->id
        ]);
        $data = [
            "checkout_at" => Carbon::now(),
            "status_id" => config('enum.status_id.READY_TO_DEPLOY'),
            'assigned_user' => $user->id,
        ];

        $I->sendPost('/hardware/'.$asset->id.'/checkin',$data);
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

        $I->sendPost('/hardware/checkout',$data);
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
            'assigned_type' => 'App\Models\User',
            'withdraw_from' => $user->id
        ]);
        $data = [
            "checkout_at" => Carbon::now(),
            "status_id" => config('enum.status_id.READY_TO_DEPLOY'),
            'assigned_user' => $user->id,
            'assets' => $assets->pluck('id')->toArray()
        ];

        $I->sendPost('/hardware/checkin',$data);
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
            'assigned_type' => 'App\Models\User'
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'send_accept' => $asset->id
        ];

        $I->sendPost('/hardware/'.$asset->id.'?_method=PUT',$data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.update.success'), $response->messages);
    }

    public function assetRejectedCheckout(ApiTester $I)
    {
        $I->wantTo('Check rejected checkout');

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
            'assigned_type' => 'App\Models\User'
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.REJECT'),
            'reason' => 'Asset reject test',
            '_method' => 'PATCH'
        ];

        $I->sendPost('/hardware/'.$asset->id,$data);
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
            'assigned_type' => 'App\Models\User',
            'withdraw_from' => $user->id
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'send_accept' => $asset->id
        ];

        $I->sendPost('/hardware/'.$asset->id.'?_method=PUT',$data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.update.success'), $response->messages);
    }

    public function assetRejectedCheckin(ApiTester $I)
    {
        $I->wantTo('Check rejected checkout');

        $location = Location::factory()->create();
        $company = Company::factory()->create();
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => $location->id
        ]);
        $asset = Asset::factory()->laptopMbp()->create([
            'name' => 'Test Asset Reject Checkin',
            'company_id' => $company->id,
            'rtd_location_id' => $location->id,
            'assigned_status' => config('enum.assigned_status.WAITINGCHECKIN'),
            'status_id' => config('enum.status_id.ASSIGN'),
            'assigned_to' => $user->id,
            'assigned_type' => 'App\Models\User',
            'withdraw_from' => $user->id
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.REJECT'),
            'reason' => 'Asset reject test',
            '_method' => 'PATCH'
        ];

        $I->sendPost('/hardware/'.$asset->id,$data);
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
            'assigned_type' => 'App\Models\User',
            'withdraw_from' => $user->id
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'assets' => $assets->pluck('id')->toArray()
        ];

        $I->sendPost('/hardware?_method=PUT',$data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.update.success'), $response->messages);
    }

    public function assetRejectedMultipleCheckin(ApiTester $I)
    {
        $I->wantTo('Check rejected multiple checkin');

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
            'assigned_type' => 'App\Models\User',
            'withdraw_from' => $user->id
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.REJECT'),
            'assets' => $assets->pluck('id')->toArray()
        ];

        $I->sendPost('/hardware?_method=PUT',$data);
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
            'assigned_type' => 'App\Models\User'
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'assets' => $assets->pluck('id')->toArray()
        ];

        $I->sendPost('/hardware?_method=PUT',$data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.update.success'), $response->messages);
    }

    public function assetRejectedMultipleCheckout(ApiTester $I)
    {
        $I->wantTo('Check rejected multiple checkin');

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
            'assigned_type' => 'App\Models\User'
        ]);
        $data = [
            'assigned_status' => config('enum.assigned_status.REJECT'),
            'assets' => $assets->pluck('id')->toArray()
        ];

        $I->sendPost('/hardware?_method=PUT',$data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.update.success'), $response->messages);
    }

    public function assetCanCheckinByTag(ApiTester $I)
    {
        $I->wantTo('Checkin an asset by tag');

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
            'assigned_type' => 'App\Models\User',
            'withdraw_from' => $user->id
        ]);
        $data = [
            "checkout_at" => Carbon::now(),
            "status_id" => config('enum.status_id.READY_TO_DEPLOY'),
            'assigned_user' => $user->id,
            'asset_tag' => $asset->asset_tag
        ];

        $I->sendPost('/hardware/checkinbytag',$data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.checkin.success'), $response->messages);
    }
}
