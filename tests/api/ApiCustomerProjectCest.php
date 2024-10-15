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
            env('BASE_API_PROJECT_URL') . '/GetAllClients' => Http::response([
                'customers' => [
                    ['id' => 1, 'name' => 'Customer 1'],
                    ['id' => 2, 'name' => 'Customer 2'],
                ],
            ], 200),
            env('BASE_API_PROJECT_URL') . '/GetAllProjects' => Http::response([
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
            'customers' => [
                ['id' => 1, 'name' => 'Customer 1'],
                ['id' => 2, 'name' => 'Customer 2'],
            ],
            'projects' => [
                ['id' => 1, 'name' => 'Project 1'],
                ['id' => 2, 'name' => 'Project 2'],
            ],
        ]);
    }
}
