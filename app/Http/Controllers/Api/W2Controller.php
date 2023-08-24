<?php

namespace App\Http\Controllers\Api;

use App\Domains\W2\Services\W2Service;
use App\Http\Controllers\Controller;
use App\Http\Transformers\W2Transformer;
use Illuminate\Http\Request;

class W2Controller extends Controller
{
    private $w2Service;

    public function __construct(W2Service $w2Service)
    {
        $this->w2Service = $w2Service;
    }

    public function getListRequest(Request $request)
    {
        $requests = $this->w2Service->getListRequest();
        return (new W2Transformer)->transformRequests(collect($requests));
    }
}
