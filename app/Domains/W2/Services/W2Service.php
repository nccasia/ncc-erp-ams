<?php

namespace App\Domains\W2\Services;

use App\Exceptions\W2Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class W2Service
{
    private $client;
    private $w2_host;
    private $w2_secret_key;

    //todo
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->w2_host = env("W2_API", "");
        $this->w2_secret_key = env("W2_SECRET_KEY", "");
    }

    private function callApiToW2($url, $method, $body = [], $headers = [])
    {
        $base_header = [
            'X-Secret-Key' => $this->w2_secret_key,
            'Content-Type' => 'application/json',
        ];
        $full_url = $this->w2_host . $url;
        $full_headers = array_merge($base_header, $headers);

        try {
            switch (Str::upper($method)) {
                case 'GET':
                    $response = $this->client->get($full_url, [
                        'headers' => $full_headers,
                        'body' => json_encode($body),
                    ]);
                    break;

                case 'POST':
                    $response = $this->client->post($full_url, [
                        'headers' => $full_headers,
                        'body' => json_encode($body),
                    ]);
                    break;

                case 'PUT':
                    $response = $this->client->put($full_url, [
                        'headers' => $full_headers,
                        'body' => json_encode($body),
                    ]);
                    break;

                case 'DELETE':
                    $response = $this->client->delete($full_url, [
                        'headers' => $full_headers,
                        'body' => json_encode($body),
                    ]);
                    break;

                default:
                    throw new HttpException(405);
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $bodyContent = json_decode($e->getResponse()->getBody()->getContents());
                throw new W2Exception($bodyContent->error->message, 400);
            } else {
                throw new HttpException(500);
            }
        }

        return json_decode($response->getBody());
    }

    private function filters(array $dataRequest)
    {
        $filters = [
            "MaxResultCount" => 10,
            "SkipCount" => 0,
            "Status" => [
                config("enum.w2.request_status.PENDING"),
                config("enum.w2.request_status.APPROVED"),
                config("enum.w2.request_status.REJECTED"),
            ],
            "RequestName" => [
                config("enum.w2.request_type.DEVICE"),
            ]
        ];

        if (Arr::exists($dataRequest, "limit")) {
            $filters["MaxResultCount"] = $dataRequest["limit"];
        }

        if (Arr::exists($dataRequest, "offset")) {
            $filters["SkipCount"] = $dataRequest["offset"];
        }

        if (Arr::exists($dataRequest, "status") && count($dataRequest["status"]) > 0) {
            $filters["Status"] = $dataRequest["status"];
        }

        return $filters;
    }

    public function getListRequest(array $dataRequest)
    {
        $body = $this->filters($dataRequest);
        $body["Email"] = Auth::user()->email;

        return $this->callApiToW2("/get-list-tasks-by-email", "post", $body);
    }

    public function approveRequest(array $dataRequest)
    {
        $body = [
            "id" => $dataRequest["id"],
            "email" => Auth::user()->email,
        ];

        return $this->callApiToW2("/approve-task", "post", $body);
    }

    public function rejectRequest(array $dataRequest)
    {
        $body = [
            "id" => $dataRequest["id"],
            "email" => Auth::user()->email,
            "reason" => $dataRequest["reason"],
        ];

        return $this->callApiToW2("/reject-task", "post", $body);
    }
}
