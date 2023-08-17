<?php

use App\Models\Location;
use App\Models\User;
use Faker\Factory;

class ApiDashboardCest
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
    public function dashboard(ApiTester $I)
    {
        $I->wantTo("get information for dashboard");
        //load dashboard success
        $I->sendGET('dashboard');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        //load dashboard fail
        $this->user->permissions = json_encode(['assets.view' => true]);
        $this->user->save();
        $I->sendGET('dashboard');
        $response = json_decode($I->grabResponse());
        $I->seeResponseCodeIs(401);
        $I->assertEquals(trans('admin/dashboard/message.not_permission'), $response->messages);
    }

    public function dashboardReportAssetByType(ApiTester $I)
    {
        $I->wantTo("view report asset by type");

        //load success
        $I->sendGET('dashboard/reportAsset', [
            'from' => '2023-08-01',
            'to' => '2023-08-10',
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        //load fail
        $this->user->permissions = json_encode(['assets.view' => true]);
        $this->user->manager_location = json_encode([Location::factory()->create()->id]);
        $this->user->save();
        $I->sendGET('dashboard/reportAsset');
        $response = json_decode($I->grabResponse());
        $I->seeResponseCodeIs(401);
        $I->assertEquals(trans('admin/dashboard/message.not_permission'), $response->messages);
    }
}
