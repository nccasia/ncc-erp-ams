<?php

namespace App\Http\Controllers\Api;

use App\Domains\W2\Services\W2Service;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\W2Transformer;
use Illuminate\Http\Request;
use Throwable;

class W2Controller extends Controller
{
    private $w2Service;

    public function __construct(W2Service $w2Service)
    {
        $this->w2Service = $w2Service;
    }

    public function getListRequest(Request $request)
    {
        try {
            $requests = $this->w2Service->getListRequest($request->all());
            return (new W2Transformer)->transformRequests(collect($requests->items), $requests->totalCount);
        } catch (Throwable $th) {
            throw $th;
        }
    }

    public function approveRequest(Request $request)
    {
        try {
            $response = $this->w2Service->approveRequest($request->all());

            return response()->json(
                Helper::formatStandardApiResponse(
                    "success",
                    ["id" => $response->id],
                    $response->message
                )
            );
        } catch (Throwable $th) {
            throw $th;
        }
    }

    public function rejectRequest(Request $request)
    {
        try {
            $response = $this->w2Service->rejectRequest($request->all());

            return response()->json(
                Helper::formatStandardApiResponse(
                    "success",
                    ["id" => $response->id],
                    $response->message
                )
            );
        } catch (Throwable $th) {
            throw $th;
        }
    }
}
