<?php

namespace App\Domains\Finfast\Services;

use App\Domains\Finfast\Models\FinfastSetting;
use GuzzleHttp\Client;

/**
 * Class AnnouncementService.
 */
// todo
class FinfastService
{
    private $finfast_uri = "";
    private $fake_user = [];


    public function __construct(FinfastSetting $finfastSetting)
    {
        $this->model = $finfastSetting;
        // todo
        $this->finfast_uri = env("FINFAST_API", "");
        $this->fake_user = [
            'userNameOrEmailAddress' => env("FINFAST_USER", ""),
            'password' => env("FINFAST_USER_PASS", ""),
            'rememberClient' => false
        ];
    }

    private function _getListEntryTypeFilter()
    {
        $data = $this->model->EntryIdFilter()->first();

        return $data != null ? $data->f_value : [];
    }

    public function saveEntryIdFilter(array $filters) {
        $model = new FinfastSetting();
        $model->setEntryFilter($filters);
        $model->save();
    }

    public function getToken() {
        $client = new Client();
        $res = $client->post($this->finfast_uri . '/TokenAuth/Authenticate', [
            'json' => $this->fake_user
        ]);
        $body = json_decode($res->getBody()->getContents());
        return $body->result->accessToken;
    }

    public function getListEntryType()
    {
        $token = $this->getToken();
        $client = new Client();
        $res = $client->get($this->finfast_uri . '/services/app/OutcomingEntryType/GetAllForDropdownByUser', [
            'headers' => [
                'Authorization' => 'Bearer '. $token
            ]
        ]);

        return json_decode($res->getBody()->getContents()); // { "type": "User", ....
    }

    // todo
    public function _getListOutcome($from, $to)
    {
        $token = self::getToken();
        $client = new Client();
        $params = [
            "filterItems" => [
                [
                    "propertyName" => "sendTime",
                    "comparision" => 4,
                    "value" => $from,
                    "filterType" => 1
                ],
                [
                    "propertyName" => "sendTime",
                    "comparision" => 2,
                    "value" => $to,
                    "filterType" => 1
                ]
            ],// todo fixed for now
            "maxResultCount" => "2000",
            "skipCount" => 0,
            "searchText" => "",
            "sort" => "",
            "sortDirection" => -1
        ];
        $res = $client->post($this->finfast_uri . '/services/app/OutcomingEntry/GetAllPaging', [
            'json' => $params,
            'headers' => [
                'Authorization' => 'Bearer '. $token
            ]
        ]);

        return json_decode($res->getBody()->getContents()); // { "type": "User", ....
    }

    public function getListOutcome($from, $to) {
        // call api
        $data = $this->_getListOutcome($from, $to);
        // phan loai data
        $entry_type_id = $this->_getListEntryTypeFilter();
        $rs = [];
        foreach ($data->result->items as $item) {
            if (in_array($item->outcomingEntryTypeId, $entry_type_id)) {
                array_push($rs, $item);
            }
        }
        // todo fake for now

        return $rs;
    }
}
