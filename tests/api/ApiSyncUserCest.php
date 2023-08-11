<?php

use App\Http\Controllers\Api\SyncListUserFromHRMController;
use App\Models\User;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Mockery;
use Psr\Http\Message\ResponseInterface;


class ApiSyncUserCest
{
    protected $mockApiUrl;
    protected $client;
    public function _before(ApiTester $I)
    {
        $this->mockApiUrl = getenv('HRM_API');
        $this->client = new Client();
    }

    // tests
    public function testSyncListUser(ApiTester $I)
    {
        // Set up a mock response for the API endpoint in the controller
        $expectedResponse = [
            'result' => [
                [
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                    'email' => 'hehe@ncc.com',
                ],
                [
                    'firstName' => 'Jane',
                    'lastName' => 'Smith',
                    'email' => 'jane@ncc.com',
                ],
            ]
        ];

        User::insert([
            'username' => "hehe",
            'first_name' => "Jane",
            'last_name' => "Doe",
            'email' => 'hehe@ncc.com',
            'permissions' => '{"superuser":"1","admin":"0","import":"0","reports.view":"0","assets.view":"0","assets.create":"0","assets.edit":"0","assets.delete":"0","assets.checkin":"0","assets.checkout":"0","assets.audit":"0","assets.view.requestable":"0","accessories.view":"0","accessories.create":"0","accessories.edit":"0","accessories.delete":"0","accessories.checkout":"0","accessories.checkin":"0","consumables.view":"0","consumables.create":"0","consumables.edit":"0","consumables.delete":"0","consumables.checkout":"0","licenses.view":"0","licenses.create":"0","licenses.edit":"0","licenses.delete":"0","licenses.checkout":"0","licenses.keys":"0","licenses.files":"0","components.view":"0","components.create":"0","components.edit":"0","components.delete":"0","components.checkout":"0","components.checkin":"0","kits.view":"0","kits.create":"0","kits.edit":"0","kits.delete":"0","kits.checkout":"0","users.view":"0","users.create":"0","users.edit":"0","users.delete":"0","models.view":"0","models.create":"0","models.edit":"0","models.delete":"0","categories.view":"0","categories.create":"0","categories.edit":"0","categories.delete":"0","departments.view":"0","departments.create":"0","departments.edit":"0","departments.delete":"0","statuslabels.view":"0","statuslabels.create":"0","statuslabels.edit":"0","statuslabels.delete":"0","customfields.view":"0","customfields.create":"0","customfields.edit":"0","customfields.delete":"0","suppliers.view":"0","suppliers.create":"0","suppliers.edit":"0","suppliers.delete":"0","manufacturers.view":"0","manufacturers.create":"0","manufacturers.edit":"0","manufacturers.delete":"0","depreciations.view":"0","depreciations.create":"0","depreciations.edit":"0","depreciations.delete":"0","locations.view":"0","locations.create":"0","locations.edit":"0","locations.delete":"0","companies.view":"0","companies.create":"0","companies.edit":"0","companies.delete":"0","self.two_factor":"0","self.api":"0","self.edit_location":"0","self.checkout_assets":"0"}'// todo
        ]);

        $mockedResponse = $this->createMockResponse(json_encode($expectedResponse), 200);
        $this->client = Mockery::mock(new Client());
        $this->client->shouldReceive('get')->with($this->mockApiUrl)->andReturn($mockedResponse);

        // Run your controller's method that makes the API call
        $controller = new SyncListUserFromHRMController($this->client);
        $request = new Request();
        $controller->syncListUser($request);
        $user1 = User::where('username', '=', 'hehe')->first();
        $user2 = User::where('email', '=', 'jane@ncc.com')->first();
        $I->assertNotEquals("Jane", $user1->first_name);
        $I->assertTrue($user1 != null);
        $I->assertTrue($user2 != null);
    }

        // Helper method to create a mock ResponseInterface
    protected function createMockResponse($body, $statusCode, $headers = [])
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->andReturn($body);
        $response->shouldReceive('getStatusCode')->andReturn($statusCode);
        $response->shouldReceive('getHeaders')->andReturn($headers);
        return $response;
    }

}
