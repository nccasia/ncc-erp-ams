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
    protected $secretKeyApiUrl;
    protected $mailDomain;
    public function _before(ApiTester $I)
    {
        $this->mockApiUrl = getenv('HRM_API');
        $this->client = new Client();
        $this->secretKeyApiUrl = getenv('HRM_SECRET_KEY');
        $this->mailDomain = getenv('MAIL_DOMAIN');
    }

    // tests
    public function testSyncListUser(ApiTester $I)
    {
        //create a exist user
        User::insert([
            'username' => "hehe",
            'first_name' => "Jane",
            'last_name' => "Doe",
            'email' => 'hehe@' . $this->mailDomain,
        ]);

        // Set up a mock response for the API endpoint in the controller
        $expectedResponse = $this->setupExpectedResponse();
        $mockedResponse = $this->createMockResponse(json_encode($expectedResponse), 200);
        $this->client = Mockery::mock(new Client());

        $this->client->shouldReceive('get')
            ->with($this->mockApiUrl, [
                'headers' => [
                    'X-Secret-Key' => $this->secretKeyApiUrl
                ]
            ])
            ->andReturn($mockedResponse);

        // Run your controller's method that makes the API call
        $controller = new SyncListUserFromHRMController($this->client);
        $request = new Request();
        $controller->syncListUser($request);

        //compare
        $mail_for_where = "jane@" . $this->mailDomain;
        $user1 = User::where('username', '=', 'hehe')->first();
        $user2 = User::where('email', '=', $mail_for_where)->first();
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

    protected function setupExpectedResponse()
    {
        return [
            'result' => [
                [
                    'email' => 'hehe@' . $this->mailDomain,
                    "fullName" => "John Doe",
                    "branchCode" => "ĐN",
                    "jobPositionCode" => "Dev",
                    "userType" => 0,
                    "userTypeName" => "TTS",
                    "status" => 1,
                    "statusName" => "Working"
                ],
                [
                    'email' => 'jane@' . $this->mailDomain,
                    "fullName" => "Jane Smith",
                    "branchCode" => "QN",
                    "jobPositionCode" => "Tester",
                    "userType" => 0,
                    "userTypeName" => "TTS",
                    "status" => 1,
                    "statusName" => "Working"
                ],
            ]
        ];
    }
}
