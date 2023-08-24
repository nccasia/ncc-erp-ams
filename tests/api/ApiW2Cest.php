<?php

use App\Domains\W2\Services\W2Service;
use App\Http\Controllers\Api\W2Controller;
use GuzzleHttp\Client;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Http\Request;

class ApiW2Cest
{
    protected $mockApiHost;
    protected $client;
    protected $fake_user = [];

    public function _before(ApiTester $I)
    {
        $this->mockApiHost = getenv('W2_API');
        $this->client = new Client();
        //todo
        $this->fake_user = [
            'userNameOrEmailAddress' => env("W2_USER", ""),
            'password' => env("W2_USER_PASS", ""),
            'rememberClient' => false
        ];
    }

    public function testGetRequestList(ApiTester $I)
    {
        //setting mock response
        $this->client = Mockery::mock(new Client());
        $expectedResponse = $this->setupExpectedResponse();

        //Token
        //todo
        $mockedToken = $this->createMockResponse(json_encode($expectedResponse["tokenFake"]), 200);
        $this->client->shouldReceive('post')
            ->with($this->mockApiHost . "/getToken", [
                'json' => $this->fake_user
            ])
            ->andReturn($mockedToken);

        //Request List
        //todo
        $mockedRequestList = $this->createMockResponse(json_encode($expectedResponse["requestListFake"]), 200);
        $this->client->shouldReceive('get')
            ->with($this->mockApiHost . "/requests/list-all", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $expectedResponse["tokenFake"]["token"],
                ]
            ])
            ->andReturn($mockedRequestList);

        //Prepare
        $service = new W2Service($this->client);
        $controller = new W2Controller($service);
        $request = new Request();

        //call
        $response = $controller->getListRequest($request);
        $I->assertEquals(2, $response['total']);

        $response = json_encode($response);
        $I->assertStringContainsString('"userRequestName":"Le Van A"', $response);
        $I->assertStringContainsString('"userRequestName":"Nguyen Van B"', $response);
        $I->assertStringNotContainsString('"userRequestName":"Doan Thi C"', $response);
        $I->assertStringContainsString('"type":"' . config("enum.w2_request_type.DEVICE") . '"', $response);
        $I->assertStringNotContainsString('"type":"WFH Request"', $response);
    }

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
            "tokenFake" => [
                "token" => "eycaa2AEC"
            ],
            "requestListFake" => [
                "totalCount" => 3,
                "items" => [
                    [
                        "id" => "3a0d2e0d-d8a0-d421-b1b6-88cfdfe8246d",
                        "workflowDefinitionId" => "3a057e11-7cde-1749-5c03-60520662a1f5",
                        "workflowDefinitionDisplayName" => "Device Request",
                        "userRequestName" => "Le Van A",
                        "createdAt" => "2023-08-22T06:14:05.216389Z",
                        "lastExecutedAt" => "2023-08-22T06:15:04.966491Z",
                        "status" => "Pending",
                        "stakeHolders" => [
                            "IT Department"
                        ],
                        "currentStates" => [
                            "IT reviews"
                        ],
                        "creatorId" => null
                    ],
                    [
                        "id" => "3a0d2d98-ae28-c417-1e3a-dd07eecbd951",
                        "workflowDefinitionId" => "3a057e11-7cde-1749-5c03-60520662a1f5",
                        "workflowDefinitionDisplayName" => "Device Request",
                        "userRequestName" => "Nguyen Van B",
                        "createdAt" => "2023-08-22T04:06:06.632796Z",
                        "lastExecutedAt" => "2023-08-22T04:14:39.904948Z",
                        "status" => "Pending",
                        "stakeHolders" => [
                            "IT Department"
                        ],
                        "currentStates" => [
                            "IT reviews"
                        ],
                        "creatorId" => null
                    ],
                    [
                        "id" => "3a0d2d06-f2a6-5478-14b3-cdeea3b5b113",
                        "workflowDefinitionId" => "3a059dc6-a381-3cc5-b6ff-7a6559d1adf7",
                        "workflowDefinitionDisplayName" => "WFH Request",
                        "userRequestName" => "Doan Thi C",
                        "createdAt" => "2023-08-22T01:26:55.910164Z",
                        "lastExecutedAt" => "2023-08-22T01:27:01.454301Z",
                        "status" => "Pending",
                        "stakeHolders" => [
                            "Hieu Nguyen Nam",
                            "Trung Do Trong",
                            "Tung Tran Huy",
                            "Trung Hoang Dinh"
                        ],
                        "currentStates" => [
                            "PM makes decision"
                        ],
                        "creatorId" => null
                    ],
                ]
            ]
        ];
    }
}
