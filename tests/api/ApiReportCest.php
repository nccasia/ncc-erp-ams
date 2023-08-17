<?php

use App\Models\Actionlog;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory;

class ApiReportCest
{
    protected $user;
    protected $faker;
    public function _before(ApiTester $I)
    {
        $this->faker = Factory::create();
        $this->user = User::factory()->create();
        $I->haveHttpHeader('Accept', 'application/json');
        $I->amBearerAuthenticated($I->getToken($this->user));
    }

    public function indexReport(ApiTester $I)
    {
        $I->wantTo('See report about assets');

        $I->sendGet('reports/activity', [
            'search' => 'h',
            'action_type' => 'checkin from',
        ]);

        $I->assertStringContainsString('checkin from', $I->grabResponse());
        $I->assertStringNotContainsString('"action_type":"update"', $I->grabResponse());

        $I->sendGet('reports/activity', [
            'date_from' => '2023-08-10',
            'date_to' => Carbon::now(),
            'target_type' => 'App\Models\User',
            'target_id' => Actionlog::where('target_type', 'App\Models\User')->inRandomOrder()->first()->target_id
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->assertStringContainsString('total', $I->grabResponse());
        $I->assertStringContainsString('rows', $I->grabResponse());
    }
}
