<?php

use App\Http\Transformers\LocationsTransformer;
use App\Models\Location;
use App\Models\User;

class ApiLocationsCest
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
    public function indexLocations(ApiTester $I)
    {
        $I->wantTo('Get a list of locations');

        // call
        $I->sendGET('/locations?limit=10');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse(), true);
        // sample verify
        $location = Location::orderByDesc('created_at')
            ->withCount('assignedAssets as assigned_assets_count', 'assets as assets_count', 'users as users_count')
            ->take(10)->get()->shuffle()->first();
        $I->seeResponseContainsJson($I->removeTimestamps((new LocationsTransformer)->transformLocation($location)));
    }

    /** @test */
    public function createLocation(ApiTester $I, $scenario)
    {
        $I->wantTo('Create a new location');

        $temp_location = Location::factory()->make([
            'name' => 'Test Location Tag',
        ]);

        // setup
        $data = [
            'name' => $temp_location->name,
            'address' => $temp_location->address,
            'address2' => $temp_location->address2,
            'city' => $temp_location->city,
            'state' => $temp_location->state,
            'country' => $temp_location->country,
            'zip' => $temp_location->zip,
            'parent_id' => $temp_location->parent_id,
            'manager_id' => $temp_location->manager_id,
            'currency' => $temp_location->currency,
            'branch_code' => $temp_location->branch_code,

        ];

        // create
        $I->sendPOST('/locations', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    /** @test */
    public function updateLocationWithPatch(ApiTester $I, $scenario)
    {
        $I->wantTo('Update an location with PATCH');

        // create
        $location = Location::factory()->create([
            'name' => 'Original Location Name',
        ]);
        $I->assertInstanceOf(Location::class, $location);

        $temp_location = Location::factory()->make([
            'name' => 'updated location name',
        ]);

        $data = [
            'name' => $temp_location->name,
            'address' => $temp_location->address,
            'address2' => $temp_location->address2,
            'city' => $temp_location->city,
            'state' => $temp_location->state,
            'country' => $temp_location->country,
            'zip' => $temp_location->zip,
            'parent_id' => $temp_location->parent_id,
            'manager_id' => $temp_location->manager_id,
            'currency' => $temp_location->currency,
            'branch_code' => $temp_location->branch_code,
            
        ];

        $I->assertNotEquals($location->name, $data['name']);

        // update
        $I->sendPATCH('/locations/'.$location->id, $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());

        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/locations/message.update.success'), $response->messages);
        $I->assertEquals($location->id, $response->payload->id); // location id does not change
        $I->assertEquals($temp_location->name, $response->payload->name); // location name updated

        // Some necessary manual copying
        $temp_location->created_at = Carbon::parse($response->payload->created_at->datetime);
        $temp_location->updated_at = Carbon::parse($response->payload->updated_at->datetime);
        $temp_location->id = $location->id;

        // verify
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson((new LocationsTransformer)->transformLocation($temp_location));
    }

    /** @test */
    public function deleteLocationTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Delete an location');

        // create
        $location = Location::factory()->create([
            'name' => 'Soon to be deleted',
        ]);
        $I->assertInstanceOf(Location::class, $location);

        // delete
        $I->sendDELETE('/locations/'.$location->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/locations/message.delete.success'), $response->messages);

        // verify, expect a 200
        $I->sendGET('/locations/'.$location->id);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }
}
