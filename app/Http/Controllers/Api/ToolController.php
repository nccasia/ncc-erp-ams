<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\ToolsTransformer;
use App\Models\Tool;
use App\Services\ToolService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ToolController extends Controller
{
    private $toolService;
    public function __construct(ToolService $toolService)
    {
        $this->toolService = $toolService;
    }

    public function index(Request $request)
    {
        $this->authorize('view', Tool::class);
        $data = $this->toolService->index($request);
        return (new ToolsTransformer)->transformTools($data['tools'], $data['total']);
    }

    public function getTotalDetail(Request $request)
    {
        $this->authorize('view', Tool::class);

        $data = $this->toolService->getTotalDetail($request);

        return response()->json(Helper::formatStandardApiResponse('success', $data, null));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Tool::class);

        $data = $this->toolService->store($request);

        if ($data['isSuccess']) {
            return response()->json(Helper::formatStandardApiResponse('success', $data['data'], trans('admin/tools/message.create.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $data['data']), Response::HTTP_BAD_REQUEST);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('update', Tool::class);

        $data = $this->toolService->update($request, $id);

        if (!$data['isSuccess']) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $data['data']), Response::HTTP_BAD_REQUEST);
        }
        return response()->json(Helper::formatStandardApiResponse('success', $data['data'], trans('admin/tools/message.update.success')));
    }

    public function multiUpdate(Request $request)
    {
        $this->authorize('update', Tool::class);
        $tools = $request->get('tools');
        foreach ($tools as $id) {
            $data = $this->toolService->update($request, $id);
            if (!$data['isSuccess']) {
                return response()->json(Helper::formatStandardApiResponse('error', null, $data['data']));
            }
        }

        return response()->json(Helper::formatStandardApiResponse('success', $data['data'], trans('admin/tools/message.update.success', ['signature' => "lol"])));
    }

    public function destroy($id)
    {
        $tool = $this->toolService->getToolById($id);

        $this->authorize('delete', $tool);

        if ($this->toolService->delete($id)) {
            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/tools/message.delete.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/tools/message.does_not_exist')), Response::HTTP_BAD_REQUEST);
    }

    public function checkout(Request $request, $tool_id)
    {
        $this->authorize('checkout', Tool::class);
        $data = $this->toolService->checkout($request, $tool_id);
        return response()->json(
            Helper::formatStandardApiResponse(
                $data['status'],
                $data['payload'],
                $data['messages']
            ),
            $data['status_code']
        );
    }

    public function multiCheckout(Request $request)
    {
        $this->authorize('checkout', Tool::class);
        $data = $this->toolService->multipleCheckout($request);
        return response()->json(
            Helper::formatStandardApiResponse(
                $data['status'],
                $data['payload'],
                $data['messages']
            ),
            $data['status_code']
        );
    }

    public function checkIn(Request $request, $tool_id)
    {
        $this->authorize('checkin', Tool::class);
        $data = $this->toolService->checkin($request, $tool_id);
        return response()->json(
            Helper::formatStandardApiResponse(
                $data['status'],
                $data['payload'],
                $data['messages']
            ),
            $data['status_code']
        );
    }

    public function multiCheckin(Request $request)
    {
        $this->authorize('checkin', Tool::class);
        $data = $this->toolService->multipleCheckin($request);
        return response()->json(
            Helper::formatStandardApiResponse(
                $data['status'],
                $data['payload'],
                $data['messages']
            ),
            $data['status_code']
        );
    }

    public function assign(Request $request)
    {
        $this->authorize('view', Tool::class);
        $data = $this->toolService->getToolAssignList($request);
        return (new ToolsTransformer)->transformTools($data['tools'], $data['total']);
    }
}
