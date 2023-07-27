<?php

use App\Exceptions\CheckoutNotAllowed;
use App\Helpers\Helper;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\Supplier;
use App\Models\User;
use Faker\Factory;
use Illuminate\Support\Facades\Auth;

class ApiCheckoutAssetsCest
{
    protected $faker;
    protected $user;
    protected $timeFormat;

    public function _before(ApiTester $I)
    {
        $this->faker = Factory::create();
        $this->user = User::factory()->create();
        $I->amBearerAuthenticated($I->getToken($this->user));
        $this->user->permissions = json_encode(["admin" => "1"]);
        $this->user->save();
    }

    /** @test */
    public function checkoutAssetToUser(ApiTester $I)
    {
        $I->wantTo('Check out an asset to a user');
        //Grab an asset from the database that isn't checked out.
        $asset = Asset::factory()->laptopAir()->create([
            'rtd_location_id' => Location::factory()->create()->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'user_id' => $this->user->id,
            'assigned_status' => 0,
            'status_id' => 5,
        ]);
        $asset->save();
        $targetUser = User::factory()->create();
        $data = [
            'assigned_user' => $targetUser->id,
            'note' => $this->faker->paragraph(),
            'expected_checkin' => '2018-05-24',
            'name' => $this->faker->name(),
            'checkout_to_type' => 'user',
        ];
        $response = $I->sendPOST("/hardware/{$asset->id}/checkout", $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.checkout.success'), $response->messages);

        // Confirm results.
        $I->sendGET("/hardware/{$asset->id}");
        $I->seeResponseContainsJson([
            'assigned_to' => [
                'id' => $targetUser->id,
                'type' => 'user',
            ],
            'name' => ($asset->name) ? $asset->name : '',
            'expected_checkin' => Helper::getFormattedDateObject('2018-05-24', 'date'),
        ]);
    }

    /** @test */
    public function checkinAsset(ApiTester $I)
    {
        $I->wantTo('Checkin an asset that is currently checked out');
        $userTarget = User::factory()->create();
        $asset = Asset::factory()->laptopAir()->create([
            'rtd_location_id' => Location::factory()->create()->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'user_id' => $this->user->id,
            'assigned_to' => $userTarget->id,
            'assigned_type'=> 'App\Models\User',
        ]);

        $I->sendPOST("/hardware/{$asset->id}/checkin", [
            'note' => 'Checkin Note',
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/hardware/message.checkin.success'), $response->messages);

        //Confirm checkin
        $I->sendPATCH("/hardware/{$asset->id}", [
            'assigned_status' => 2,
            'send_accept' => $asset->id,
        ]);

        // Verify
        $I->sendGET("/hardware/{$asset->id}");
        $I->seeResponseContainsJson([
            'assigned_to' => null,
        ]);
    }
}
