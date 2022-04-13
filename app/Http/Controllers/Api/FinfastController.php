<?php

namespace App\Http\Controllers\Api;

use App\Domains\Finfast\Services\FinfastService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FinfastController extends Controller
{
    /**
     * @var FinfastService
     */
    private FinfastService $finfastService;

    public function __construct(FinfastService $finfastService)
    {
        $this->finfastService = $finfastService;
    }
    public function getFinfast(Request $request) {
        $from = $request->from;
        $to = $request->to;
        return $this->finfastService->getListOutcome($from, $to);
    }
    public function getListEntryType() {
        return $this->finfastService->getListEntryType();
    }
    public function saveEntryIdFilter(Request $request) {
        return $this->finfastService->saveEntryIdFilter(json_decode($request->value));
    }
    public function getEntryIdFilter() {
        return $this->finfastService->getEntryIdFilter();
    }
    public function getBranch() {
        $data['rows'] = $this->finfastService->getBranch()->result;
        $data['total'] = count($data['rows']);
        return $data;
    }
    public function getSupplier() {
        $data['rows'] = $this->finfastService->getSupplier()->result;
        $data['total'] = count($data['rows']);
        return $data;       
    }
}
