<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

class ApiCustomerProjectCest
{
    protected $user;

    public function _before(ApiTester $I)
    {
        $this->user = User::factory()->create();
        $I->haveHttpHeader('Accept', 'application/json');
        $I->amBearerAuthenticated($I->getToken($this->user));

        Http::fake([
            env('CUSTOMER_API_URL') => Http::response([
                'customers' => [
                    ['id' => 1, 'name' => 'Customer 1'],
                    ['id' => 2, 'name' => 'Customer 2'],
                ],
            ], 200),
            env('PROJECT_API_URL') => Http::response([
                'projects' => [
                    ['id' => 1, 'name' => 'Project 1'],
                    ['id' => 2, 'name' => 'Project 2'],
                ],
            ], 200),
        ]);
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
