<?php

use App\Domains\W2\Services\W2Service;
use App\Exceptions\W2Exception;
use App\Http\Controllers\Api\W2Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiW2Cest
{
    protected $mockApiHost;
    protected $client;
    protected $w2_secret_key;

    public function _before(ApiTester $I)
    {
        $this->mockApiHost = getenv('W2_API') ?? "";
        $this->w2_secret_key = getenv('W2_SECRET_KEY' ?? "");
        $this->client = new Client();
        Auth::shouldReceive('user')->andReturn((object) ['email' => 'test@example.com']);
    }

    public function testGetRequestList(ApiTester $I)
    {
        $this->client = Mockery::mock(new Client());
        $expectedResponse = $this->setupExpectedResponse();
        $mockedRequestList = $this->createMockResponse(json_encode($expectedResponse["requestListFake"]), 200);

        $this->client->shouldReceive("post")
            ->with($this->mockApiHost . "/get-list-tasks-by-email", [
                'headers' => [
                    'X-Secret-Key' => $this->w2_secret_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    "MaxResultCount" => 10,
                    "SkipCount" => 0,
                    "Status" => [0, 1, 2],
                    "RequestName" => ["Device Request"],
                    "Email" => "test@example.com"
                ])
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
        $I->assertStringContainsString('"type":"' . config("enum.w2.request_type.DEVICE") . '"', $response);
        $I->assertStringNotContainsString('"type":"WFH Request"', $response);
    }

    public function testApproveRequestSuccess(ApiTester $I)
    {
        //setting mock response
        $this->client = Mockery::mock(new Client());
        $expectedResponse = $this->setupExpectedResponse();
        $mockedRequestList = $this->createMockResponse(
            json_encode($expectedResponse["ApproveRequestFake"]["success"]),
            200
        );

        $this->client->shouldReceive("post")
            ->with($this->mockApiHost . "/approve-task", [
                'headers' => [
                    'X-Secret-Key' => $this->w2_secret_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    "id" => "abc",
                    "email" => "test@example.com",
                ])
            ])
            ->andReturn($mockedRequestList);

        //Prepare
        $service = new W2Service($this->client);
        $controller = new W2Controller($service);
        $request = new Request();
        $request->merge(["id" => "abc"]);

        $response = $controller->approveRequest($request);
        $I->assertInstanceOf("Illuminate\Http\JsonResponse", $response);

        //compare
        $response = json_decode($response->getContent(), true);
        $I->assertEquals("success", $response['status']);
        $I->assertEquals("Approve Request Successfully!", $response['messages']);
        $I->assertEquals("abc", $response['payload']['id']);
    }

    public function testApproveRequestFail(ApiTester $I)
    {
        // setting mock response
        $this->client = Mockery::mock(new Client());
        $expectedResponse = $this->setupExpectedResponse();

        $exception = $this->createMockException(
            "Exception:MyTaskNotValid",
            $expectedResponse["ApproveRequestFake"]["error"]
        );

        $this->client->shouldReceive("post")
            ->with($this->mockApiHost . "/approve-task", [
                'headers' => [
                    'X-Secret-Key' => $this->w2_secret_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    "id" => "abc",
                    "email" => "test@example.com",
                ])
            ])
            ->andThrowExceptions([$exception]);

        // Prepare
        $service = new W2Service($this->client);
        $controller = new W2Controller($service);
        $request = new Request();
        $request->merge(["id" => "abc"]);

        // Compare
        try {
            $controller->approveRequest($request);
        } catch (W2Exception $e) {
            $I->assertEquals("Exception:MyTaskNotValid", $e->getMessage());
            $I->assertNull($e->getPayload());
            $I->assertEquals(400, $e->getStatusCode());
        }
    }

    public function testRejectRequestSuccess(ApiTester $I)
    {
        //setting mock response
        $this->client = Mockery::mock(new Client());
        $expectedResponse = $this->setupExpectedResponse();
        $mockedRequestList = $this->createMockResponse(
            json_encode($expectedResponse["RejectRequestFake"]["success"]),
            200
        );

        $this->client->shouldReceive("post")
            ->with($this->mockApiHost . "/reject-task", [
                'headers' => [
                    'X-Secret-Key' => $this->w2_secret_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    "id" => "abc",
                    "email" => "test@example.com",
                    "reason" => "hi",
                ])
            ])
            ->andReturn($mockedRequestList);

        //Prepare
        $service = new W2Service($this->client);
        $controller = new W2Controller($service);
        $request = new Request();
        $request->merge(["id" => "abc", "reason" => "hi"]);

        //compare
        $response = $controller->rejectRequest($request);
        $I->assertInstanceOf("Illuminate\Http\JsonResponse", $response);

        $response = json_decode($response->getContent(), true);
        $I->assertEquals("success", $response['status']);
        $I->assertEquals("Reject Request Successfully!", $response['messages']);
        $I->assertEquals("abc", $response['payload']['id']);
    }

    public function testRejectRequestFail(ApiTester $I)
    {
        //setting mock response
        $this->client = Mockery::mock(new Client());
        $expectedResponse = $this->setupExpectedResponse();
        $exception = $this->createMockException(
            "Exception:MyTaskNotValid",
            $expectedResponse["ApproveRequestFake"]["error"]
        );

        $this->client->shouldReceive("post")
            ->with($this->mockApiHost . "/reject-task", [
                'headers' => [
                    'X-Secret-Key' => $this->w2_secret_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    "id" => "abc",
                    "email" => "test@example.com",
                    "reason" => "hi",
                ])
            ])
            ->andThrowExceptions([$exception]);

        //Prepare
        $service = new W2Service($this->client);
        $controller = new W2Controller($service);
        $request = new Request();
        $request->merge(["id" => "abc", "reason" => "hi"]);

        //compare
        try {
            $controller->rejectRequest($request);
        } catch (W2Exception $e) {
            $I->assertEquals("Exception:MyTaskNotValid", $e->getMessage());
            $I->assertNull($e->getPayload());
            $I->assertEquals(400, $e->getStatusCode());
        }
    }

    protected function createMockResponse($body, $statusCode, $headers = [])
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->andReturn($body);
        $response->shouldReceive('getStatusCode')->andReturn($statusCode);
        $response->shouldReceive('getHeaders')->andReturn($headers);
        return $response;
    }

    protected function createMockException(string $message, array $expectedResponse)
    {
        return new RequestException(
            $message,
            new Psr7Request('POST', $this->mockApiHost . "/approve-task"),
            new Response(400, [], json_encode($expectedResponse))
        );
    }

    protected function setupExpectedResponse()
    {
        return [
            "requestListFake" => [
                "totalCount" => 2,
                "items" => [
                    [
                        "id" => "3a0e817b-bc56-c9e1-ebd9-94a70bd752ce",
                        "workflowInstanceId" => "3a0e7d0a-8b2f-9e2e-1f29-acb934518746",
                        "workflowDefinitionId" => "3a057e11-7cde-1749-5c03-60520662a1f5",
                        "email" => "haibon@example.abc",
                        "status" => 1,
                        "name" => "Device Request",
                        "description" => "Sender Email and assign email",
                        "dynamicActionData" => null,
                        "reason" => null,
                        "creationTime" => "2023-10-27T04:05:23.162274Z",
                        "otherActionSignals" => null,
                        "emailTo" => [
                            "hi@example.abc"
                        ],
                        "author" => "81676b37-5ab2-469a-b04b-63aaedb34b40",
                        "authorName" => "Le Van A",
                    ],
                    [
                        "id" => "3a0e817b-bc56-c9e1-ebd9-94a70bd752ce",
                        "workflowInstanceId" => "3a0e7d0a-8b2f-9e2e-1f29-acb934518746",
                        "workflowDefinitionId" => "3a057e11-7cde-1749-5c03-60520662a1f5",
                        "email" => "haiba@example.abc",
                        "status" => 0,
                        "name" => "Device Request",
                        "description" => "Sender Email and assign email",
                        "dynamicActionData" => null,
                        "reason" => null,
                        "creationTime" => "2023-10-27T04:05:23.162274Z",
                        "otherActionSignals" => null,
                        "emailTo" => [
                            "hi2@example.abc"
                        ],
                        "author" => "81676b37-5ab2-469a-b04b-63aaedb34b41",
                        "authorName" => "Nguyen Van B",
                    ],
                ]
            ],
            "ApproveRequestFake" => [
                "success" => [
                    "id" => "abc",
                    "message" => "Approve Request Successfully!"
                ],
                "error" => [
                    "error" => [
                        "code" => null,
                        "message" => "Exception:MyTaskNotValid",
                        "details" => null,
                        "data" => [],
                        "validationErrors" => null
                    ]
                ],
            ],
            "RejectRequestFake" => [
                "success" => [
                    "id" => "abc",
                    "message" => "Reject Request Successfully!"
                ],
                "error" => [
                    "error" => [
                        "code" => null,
                        "message" => "Exception:MyTaskNotValid",
                        "details" => null,
                        "data" => [],
                        "validationErrors" => null
                    ]
                ],
            ],
        ];
    }
}
