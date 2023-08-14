<?php

use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\License;
use App\Models\Supplier;
use App\Models\User;
use Faker\Factory;

class ApiSuppliersCest
{
    protected $faker;
    protected $user;
    public function _before(ApiTester $I)
    {
        $this->faker = Factory::create();
        $this->user = User::factory()->create();
        $I->haveHttpHeader('Accept', 'application/json');
        $I->amBearerAuthenticated($I->getToken($this->user));
    }

    // tests
    public function indexSupplier(ApiTester $I)
    {
        $I->wantTo("Get a list of Supplier");
        
        //call
        $I->sendGET("suppliers", [
            'search' => 'h',
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function storeSupplier(ApiTester $I)
    {
        $I->wantTo("Create a new supplier");

        //prepare data
        $data = [
            'name' => $this->faker->company(),
            'address' => $this->faker->address(),
            'contact' => $this->faker->text(50),
            'phone' => $this->faker->phoneNumber(),
        ];

        //create success
        $I->sendPOST('/suppliers', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        
        //create fail(
        $data['name'] = null;
        $I->sendPOST('/suppliers', $data);
        $I->seeResponseCodeIs(400);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals('The name field is required.', $response->messages->name[0]);
    }

    public function showSupplier(ApiTester $I)
    {
        $I->wantTo("Get detail of supplier");

        $supplier = Supplier::factory()->create();

        $I->sendGET("/suppliers/{$supplier->id}");
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse());
        $I->assertEquals($supplier->name, $response->name);
    }

    public function updateSupplier(ApiTester $I)
    {
        $I->wantTo("Update supplier using method patch");

        $supplier = Supplier::factory()->create();
        $data_update = [
            'name' => $this->faker->company(),
            'address' => $this->faker->address(),
            'contact' => $this->faker->text(50),
            'phone' => $this->faker->phoneNumber(),
        ];

        //update success
        $I->sendPATCH("/suppliers/{$supplier->id}", $data_update);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse());
        $supplier = Supplier::find($supplier->id);
        $I->assertEquals($response->payload->name, $supplier->name);
        $I->assertEquals($response->payload->phone, $supplier->phone);

        //update error
        $data_update['name'] = null;
        $I->sendPATCH("/suppliers/{$supplier->id}", $data_update);
        $I->seeResponseCodeIs(400);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals('The name field is required.', $response->messages->name[0]);
    }

    public function deleteSupplier(ApiTester $I)
    {
        $I->wantTo("Delete A Supplier");

        $supplier = Supplier::factory()->create();

        //delete success
        $I->sendDelete("suppliers/{$supplier->id}");
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/suppliers/message.delete.success'), $response->messages);
        $I->assertNull($response->payload);

        //delete err
        $supplier_err = Supplier::factory()->create();

        //because of assets
        $asset = Asset::factory()->laptopMbp()->create(['supplier_id' => $supplier_err->id]);
        $I->sendDelete("suppliers/{$supplier_err->id}");
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertNull($response->payload);
        $I->assertStringContainsString($supplier_err->assets->count(), $response->messages);
        $asset = Asset::find($asset->id);
        $asset->supplier_id = Supplier::factory()->create()->id;
        $asset->save();

        //because of asset maintenances
        $assetMaintenance = AssetMaintenance::factory()->create([
            'asset_id' => $asset->id,
            'supplier_id' => $supplier_err->id,
        ]);
        $I->sendDelete("suppliers/{$supplier_err->id}");
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertNull($response->payload);
        $I->assertStringContainsString($supplier_err->asset_maintenances->count(), $response->messages);
        $assetMaintenance = AssetMaintenance::find($assetMaintenance->id);
        $assetMaintenance->supplier_id = Supplier::factory()->create()->id;
        $assetMaintenance->save();

        //because of licenses
        $license = License::factory()->office()->create([
            'supplier_id' => $supplier_err->id,
        ]);
        $I->sendDelete("suppliers/{$supplier_err->id}");
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertNull($response->payload);
        $I->assertStringContainsString($supplier_err->licenses->count(), $response->messages);
    }

    public function selectlistSupplier(ApiTester $I)
    {
        $I->wantTo('Select list of supplier');
        $I->sendGET('/suppliers/selectlist', [
            'search' => 'k',
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }
}
