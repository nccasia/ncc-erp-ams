<?php

use App\Models\User;

class ApiCustomerProjectCest
{
    protected $user;

    public function _before(ApiTester $I)
    {
        $this->user = User::factory()->create();
        $I->haveHttpHeader('Accept', 'application/json');
        $I->amBearerAuthenticated($I->getToken($this->user));
    }

    /** @test */
    public function getCustomersAndProjects(ApiTester $I)
    {
        $I->wantTo('Get a list of customers and projects');

        $I->sendGET('/customer-project');

        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $I->seeResponseContainsJson([
            'customers' => [],
            'projects' => []
        ]);
        $response = json_decode($I->grabResponse(), true);

        $I->assertNotEmpty($response['customers'], 'Customer list should not be empty.');
        $I->assertNotEmpty($response['projects'], 'Project list should not be empty.');
    }
}
