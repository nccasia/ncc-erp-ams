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
            'action_type' => 'checkout',
        ]);

        $I->assertStringContainsString('checkout', $I->grabResponse());
        $I->assertStringNotContainsString('"action_type":"update"', $I->grabResponse());
        $I->assertStringNotContainsString('"action_type":"checkin from"', $I->grabResponse());

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

    public function totalDetailReport(ApiTester $I)
    {
        $I->wantTo('Get total detail for reports');

        $filter = '?target_type=' . 'App\\Models\\User'
            . '&item_type=' . 'App\\Models\\Asset'
            . '&target_id=' . '1'
            . '&item_id=' . '1'
            . '&date_from=' . '2023-08-10'
            . '&date_to=' . Carbon::now()
            . '&action_type=' . 'checkout'
            . '&search=' . 'admin';
        $I->sendGET('reports/activity/total-detail' . $filter);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }
}
