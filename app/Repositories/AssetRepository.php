<?php

namespace App\Repositories;

use App\Exceptions\AssetException;
use App\Helpers\DateFormatter;
use App\Helpers\Helper;
use App\Http\Traits\ConvertsBase64ToFiles;
use App\Jobs\SendConfirmMail;
use App\Jobs\SendConfirmRevokeMail;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AssetRepository
{
    use ConvertsBase64ToFiles;
    private $asset;

    public function __construct(Asset $asset)
    {
        $this->asset = $asset;
    }

    public function getAllowedColumns()
    {
        return [
            'id',
            'name',
            'asset_tag',
            'serial',
            'model',
            'category',
            'status_label',
            'assigned_to',
            'location',
            'rtd_location',
            'manufacturer',
            'supplier',
            'purchase_date',
            'order_number',
            'warranty_months',
            'notes',
            'checkout_counter',
            'checkin_counter',
            'requestable',
            'assigned_status',
            'created_at',
        ];
    }

    public function getFunctionSort()
    {
        return [
            'model' => 'OrderModels',
            'category' => 'OrderCategory',
            'manufacturer' => 'OrderManufacturer',
            'company' => 'OrderCompany',
            'location' => 'OrderLocation',
            'rtd_location' => 'OrderRtdLocation',
            'status_label' => 'OrderStatus',
            'supplier' => 'OrderSupplier',
            'assigned_to' => 'OrderAssigned',
        ];
    }

    public function sortAssets($assets, $data)
    {
        $order = $data['order'] ?? "asc";
        $sort_override = $data['sort'] ?? "id";

        $column_sort = Arr::exists($this->getAllowedColumns(), $sort_override) ? $sort_override : 'assets.created_at';
        $functionSort = $this->getFunctionSort();

        if (isset($functionSort[$sort_override])) {
            $assets->{$functionSort[$sort_override]}($order);
        } else {
            $assets->orderBy($column_sort, $order);
        }

        return $assets;
    }

    public function filters($assets, array $data, bool $is_external)
    {
        $filter = [];

        $assets->filterAssetByRole(Auth::user());

        if (Arr::exists($data, 'IS_WAITING_PAGE') && $data['IS_WAITING_PAGE']) {
            $assets->where(function ($query) {
                $query->where('assets.assigned_status', '=', config('enum.assigned_status.WAITINGCHECKOUT'))
                    ->orWhere('assets.assigned_status', '=', config('enum.assigned_status.WAITINGCHECKIN'));
            });
        }

        if (Arr::exists($data, 'assigned_status')) {
            $assets->InAssignedStatus($data['assigned_status'], $is_external);
        }

        if (Arr::exists($data, 'status_id')) {
            $assets->where('assets.status_id', '=', $data['status_id']);
        }

        if (Arr::exists($data, 'model_id')) {
            $assets->InModelList([$data['model_id']]);
        }

        if (Arr::exists($data, 'category_id')) {
            $assets->InCategory($data['category_id'], $is_external);
        }

        if (Arr::exists($data, 'location_id')) {
            $assets->where('assets.location_id', '=', $data['location_id']);
        }

        if (Arr::exists($data, 'dateFrom') && Arr::exists($data, 'dateTo')) {
            $assets->whereBetween('assets.purchase_date', [$data['dateFrom'], $data['dateTo']]);
        }

        if (Arr::exists($data, 'dateCheckoutFrom') && Arr::exists($data, 'dateCheckoutTo')) {
            $dataByCheckoutDate = DateFormatter::formatDate($data['dateCheckoutFrom'], $data['dateCheckoutTo']);
            $assets->whereBetween('assets.last_checkout', [$dataByCheckoutDate]);
        }

        if (Arr::exists($data, 'supplier_id')) {
            $assets->where('assets.supplier_id', '=', $data['supplier_id']);
        }

        if ((Arr::exists($data, 'assigned_to')) && (Arr::exists($data, 'assigned_type'))) {
            $assets->where('assets.assigned_to', '=', $data['assigned_to'])
                ->where('assets.assigned_type', '=', $data['assigned_type']);
        }

        if (Arr::exists($data, 'company_id')) {
            $assets->where('assets.company_id', '=', $data['company_id']);
        }

        if (Arr::exists($data, 'category')) {
            $assets->InCategory($data['category'], $is_external);
        }

        if (Arr::exists($data, 'status_label')) {
            $assets->InStatus($data['status_label'], $is_external);
        }

        if (Arr::exists($data, 'manufacturer_id')) {
            $assets->ByManufacturer($data['manufacturer_id']);
        }

        if (Arr::exists($data, 'from')) {
            $from = Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay()->toDateTimeString();
            $assets = $assets->where('created_at', '>=', $from);
        }

        if (Arr::exists($data, 'to')) {
            $to = Carbon::createFromFormat('Y-m-d', $data['to'])->endOfDay()->toDateTimeString();
            $assets = $assets->where('created_at', '<=', $to);
        }

        $assets = $assets->where('assets.is_external', '=', $is_external);

        if (Arr::exists($filter, 'order_number')) {
            $assets = $assets->where('assets.order_number', '=', e($data['order_number']));
        }

        if (!is_null($filter) && count($filter) > 0) {
            $assets->ByFilter($filter);
        } elseif (Arr::exists($data, 'search')) {
            $assets->TextSearch($data['search']);
        }

        $assets = $this->sortAssets($assets, $data);

        return $assets;
    }

    public function getListAssets(array $data, bool $is_external)
    {
        $assets = Company::scopeCompanyables(Asset::select('assets.*'), 'company_id', 'assets')
            ->with(
                'location',
                'assetstatus',
                'company',
                'defaultLoc',
                'assignedTo',
                'model.category',
                'model.manufacturer',
                'model.fieldset',
                'supplier'
            );

        $assets = $this->filters($assets, $data, $is_external);

        if (
            $assets &&
            Arr::exists($data, 'offset') &&
            $data['offset'] > $assets->count()
        ) {
            $offset = $assets->count();
        } else {
            $offset = $data['offset'] ?? 0;
        }

        if (
            Arr::exists($data, 'limit') &&
            config('app.max_results') >= $data['limit']
        ) {
            $limit = $data['limit'];
        } else {
            $limit = config('app.max_results');
        }

        $total = $assets->count();

        $assets = $assets->skip($offset)->take($limit)->get();

        return [
            'total' => $total,
            'assets' => $assets,
        ];
    }

    public function getTotalDetail(array $data, bool $is_external)
    {
        $assets = Company::scopeCompanyables(Asset::select('assets.*'), 'company_id', 'assets');
        $assets = $this->filters($assets, $data, $is_external);

        $total_asset_by_model = $assets->selectRaw('c.name as category_name , count(*) as total')
            ->join('models as m', 'assets.model_id', '=', 'm.id')
            ->join('categories as c', 'm.category_id', '=', 'c.id')
            ->groupBy('category_name')
            ->pluck('total', 'category_name');

        return $total_asset_by_model;
    }

    public function setValueForModel($asset, array $data, $is_external)
    {
        $asset->model_id = (int) ($data['model_id'] ?? '');
        $asset->name = $data['name'] ?? '';
        $asset->serial = $data['serial'] ?? '';
        $asset->order_number = $data['order_number'] ?? '';
        $asset->notes = $data['notes'] ?? '';
        $asset->asset_tag = $data['asset_tag'] ?? Asset::autoincrement_asset();
        $asset->user_id = Auth::id();
        $asset->warranty_months = $data['warranty_months'] ?? null;
        $asset->purchase_date = $data['purchase_date'] ?? null;
        $asset->supplier_id = $data['supplier_id'] ?? 0;
        $asset->requestable = $data['requestable'] ?? 0;
        $asset->rtd_location_id = $data['rtd_location_id'] ?? null;
        $asset->location_id = $data['location_id'] ?? null;
        $asset->is_external = $is_external;

        if (Arr::exists($data, 'status_id')) {
            $asset->status_id = $data['status_id'];
        }

        if (Arr::exists($data, 'purchase_cost')) {
            $asset->purchase_cost = Helper::ParseCurrency($data['purchase_cost']);
        }

        if (Arr::exists($data, 'company_id')) {
            $asset->company_id = Company::getIdForCurrentUser($data['company_id']);
        }

        if (Arr::exists($data, 'model_id')) {
            $model = AssetModel::find((int) $data['model_id']);
            $asset->model()->associate($model);
        }

        return $asset;
    }

    public function store(array $data, bool $is_external)
    {
        $this->asset = $this->setValueForModel($this->asset, $data, $is_external);

        if (!$this->asset->save()) {
            throw new AssetException($this->asset->getErrors(), 'error', 500);
        }

        return $this->asset;
    }

    public function updateAssetAssignStatus($asset, $data)
    {
        $user = null;
        $assigned_status = $asset->assigned_status;

        if ($asset->assigned_to) {
            $user = User::find($asset->assigned_to);
        }

        if (
            $user &&
            Arr::exists($data, 'assigned_status') &&
            $assigned_status !== $data['assigned_status']
        ) {
            $asset->assigned_status = $data['assigned_status'];
            $it_ncc_email = Setting::first()->admin_cc_email;
            $user_name = $user->getFullNameAttribute();
            $current_time = Carbon::now();
            $dataPrepare = [
                'user_name' => $user_name,
                'is_confirm' => '',
                'asset_name' => $asset->name,
                'time' => $current_time->format('d-m-Y'),
                'reason' => '',
            ];

            if ($asset->assigned_status === config('enum.assigned_status.ACCEPT')) {
                $dataPrepare['asset_count'] = 1;

                //Check confirm checkin
                if ($asset->withdraw_from) {
                    $asset->checkin_counter += 1;
                    $dataPrepare['is_confirm'] = 'đã xác nhận thu hồi';
                    if (
                        $asset->status_id != config('enum.status_id.PENDING') &&
                        $asset->status_id != config('enum.status_id.BROKEN')
                    ) {
                        $asset->status_id = config('enum.status_id.READY_TO_DEPLOY');
                    }
                    $asset->assigned_status = config('enum.assigned_status.DEFAULT');
                    $asset->withdraw_from = null;
                    $asset->expected_checkin = null;
                    $asset->last_checkout = null;
                    $asset->assigned_to = null;
                    $asset->assignedTo()->disassociate($this);
                    $asset->accepted = null;
                    SendConfirmRevokeMail::dispatch($dataPrepare, $it_ncc_email);
                }

                //confirm checkout
                else {
                    $asset->checkout_counter += 1;
                    $dataPrepare['is_confirm'] = 'đã xác nhận cấp phát';
                    $asset->status_id = config('enum.status_id.ASSIGN');
                    SendConfirmMail::dispatch($dataPrepare, $it_ncc_email);
                }
            }
        }

        return $asset;
    }

    public function update(array $data, bool $is_external, $id = null)
    {
        $result_assets = [];

        if (!Arr::exists($data, 'assigned_status')) {
            $assetsToProcess = [$id];
        } else {
            $assetsToProcess = $data['assets'] ?? [$id];
        }

        foreach ($assetsToProcess as $asset_id) {
            $this->asset = Asset::find($asset_id);

            if (!$this->asset) {
                throw new AssetException(trans('admin/hardware/message.does_not_exist'), 'error', 404);
            }

            // Update asset values based on the data
            $this->asset = !Arr::exists($data, 'assigned_status')
                ? $this->setValueForModel($this->asset, $data, $is_external)
                : $this->updateAssetAssignStatus($this->asset, $data);

            // Save the asset
            if (!$this->asset->save()) {
                throw new AssetException($this->asset->getErrors(), 'error', 400);
            }

            $result_assets[] = $this->asset;
        }

        return $result_assets;
    }

    public function destroy($id)
    {
        $this->asset = Asset::find($id);

        if (!$this->asset) {
            throw new AssetException(trans('admin/hardware/message.does_not_exist'), 'error', 404);
        }

        return $this->asset->delete();
    }

    public function getAssetById($id)
    {
        $this->asset = Asset::find($id);

        if (!$this->asset) {
            throw new AssetException(trans('admin/hardware/message.does_not_exist'), 'error', 404);
        }

        return $this->asset;
    }
}
