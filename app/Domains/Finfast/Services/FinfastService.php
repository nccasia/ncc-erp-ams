<?php

namespace App\Domains\Finfast\Services;

use App\Domains\Finfast\Models\FinfastSetting;
use App\Models\FinfastRequest;
use App\Models\FinfastRequestAsset;
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

    public function _getListEntryTypeFilter()
    {
        $data = $this->model->EntryIdFilter()->first();

        return $data != null ? $data->f_value : [];
    }

    public function saveEntryIdFilter(array $filters) {
        $model = new FinfastSetting();
        $model->setEntryFilter($filters);
        $finfastSetting = FinfastSetting::where('f_key','EntryFilter')->first();
        $finfastSetting->update($model->toArray());
        return $finfastSetting;
    }

    public function getEntryIdFilter() {
        return json_decode($this->_getListEntryTypeFilter());
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

        return json_decode($res->getBody()->getContents());
    }

    public  function  findEntryType($id){
        $entryTypes = $this->getListEntryType();

        foreach ($entryTypes->result as $item){
            if ($item->id = $id) return $item;
        }
        return [];
    }

    public  function  getBranch(){
        $token = $this->getToken();
        $client = new Client();
        $res = $client->get($this->finfast_uri . '/services/app/Branch/GetAllForDropdown', [
            'headers' => [
                'Authorization' => 'Bearer '. $token
            ]
        ]);

        return json_decode($res->getBody()->getContents());
    }

    public  function  findBranch($id){
        $branchs = $this->getBranch();

        foreach ($branchs->result as $item){
            if ($item->id == $id) return $item;
        }
        return [];
    }


    public  function  getSupplier(){
        $token = $this->getToken();
        $client = new Client();
        $res = $client->get($this->finfast_uri . '/services/app/Supplier/GetAllForDropdown', [
            'headers' => [
                'Authorization' => 'Bearer '. $token
            ]
        ]);

        return json_decode($res->getBody()->getContents());
    }

    public  function  findSupplier($id){
       $suppliers = $this->getSupplier();

       foreach ($suppliers->result as $item){
           if ($item->id = $id) return $item;
       }
        return [];
    }

    protected function _buildTree(array $elements, int $parentId = null)
    {
        $branch = array();
        foreach ($elements as $element) {
            if ($element->parentId ==  $parentId) {
                $children = self::_buildTree($elements, $element->id);
                if ($children) {
                    $element->children = $children;
                }
                $branch[] = $element;
            }
        }
        return $branch;
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
        $entry_type_id = json_decode($this->_getListEntryTypeFilter());
        $rs = [];
        foreach ($data->result->items as $item) {
            if (in_array($item->outcomingEntryTypeId, $entry_type_id)) {
                array_push($rs, $item);
            }
        }
        // todo fake for now

        return $rs;
    }

    public function createOutcome($finfast_request_id){
        $finfast_request = FinfastRequest::find($finfast_request_id);
        if ($finfast_request->status !== config('enum.request_status.PENDING')) return false;
        $finfast_request_assets = FinfastRequestAsset::where("finfast_request_id", $finfast_request_id)->with('asset')->get();
        $value = 0;
        foreach ($finfast_request_assets as $item){
            $value = $value + $item->asset->purchase_cost;
        }
        $token = $this->getToken();
        $client = new Client();
        $res = $client->post($this->finfast_uri . '/services/app/OutcomingEntry/Create', [
            'headers' => [
                'Authorization' => 'Bearer '. $token
            ],
            'json' =>
                [
                    //todo must update currencyId = 10004 accountId = 10007
                    "currencyId" => 10004,
                    "accountId" => 10007,
                    "name" => $finfast_request->name,
                    "outcomingEntryTypeId" => $finfast_request->entry_id,
                    "branchId" => $finfast_request->branch_id,
                    "supplierId" => $finfast_request->supplier_id,
                    "value" => $value
                ]
        ]);

        $finfast_request->update([
            "status" => config('enum.request_status.SENT'),
        ]);

        return json_decode($res->getBody()->getContents());
    }
}
