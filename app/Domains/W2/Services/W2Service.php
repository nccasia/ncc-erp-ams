<?php

namespace App\Domains\W2\Services;

use GuzzleHttp\Client;

class W2Service
{
    private $client;
    private $w2_host = "";
    private $fake_user = [];

    //todo
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->w2_host = env("W2_API", "");
        $this->fake_user = [
            'userNameOrEmailAddress' => env("W2_USER", ""),
            'password' => env("W2_USER_PASS", ""),
            'rememberClient' => false
        ];
    }

    //todo
    protected function getToken()
    {
        $full_uri = $this->w2_host . "/getToken";
        $res = $this->client->post($full_uri, [
            'json' => $this->fake_user
        ]);
        $body = json_decode($res->getBody());
        return $body->token;
    }

    //todo
    public function callApiListRequest()
    {
        $res = $this->client->get($this->w2_host . "/requests/list-all", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken()
            ]
        ]);
        return json_decode($res->getBody());
    }

    public function getListRequest()
    {
        $requests = $this->callApiListRequest();

        $res = [];

        foreach ($requests->items as $item) {
            if (
                $item->workflowDefinitionDisplayName == config("enum.w2_request_type.DEVICE") ||
                $item->workflowDefinitionDisplayName == config("enum.w2_request_type.EQUIPMENT")
            ) {
                $res[] = $item;
            }
        }

        return $res;
    }
}
