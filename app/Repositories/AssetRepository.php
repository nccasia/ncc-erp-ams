<?php

namespace App\Repositories;

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
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Intervention\Image\Facades\Image;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
            'model_number',
            'last_checkout',
            'notes',
            'expected_checkin',
            'order_number',
            'image',
            'assigned_to',
            'created_at',
            'updated_at',
            'purchase_date',
            'purchase_cost',
            'last_audit_date',
            'next_audit_date',
            'assigned_status',
            'requestable',
            'warranty_months',
            'checkout_counter',
            'checkin_counter',
            'requests_counter',
        ];
    }

    public function sortAssets($assets, $data)
    {
        $order = "desc";
        if (Arr::exists($data, 'order')) {
            $order = $data['order'] === 'asc' ? 'asc' : 'desc';
        }

        $sort_override = "id";
        if (Arr::exists($data, 'sort')) {
            $sort_override = Str::replace('custom_fields', '', $data['sort']);
        }

        $column_sort = Arr::exists($this->getAllowedColumns(), $sort_override) ? $sort_override : 'assets.created_at';

        switch ($sort_override) {
            case 'model':
                $assets->OrderModels($order);
                break;

            case 'model_number':
                $assets->OrderModelNumber($order);
                break;

            case 'category':
                $assets->OrderCategory($order);
                break;

            case 'manufacturer':
                $assets->OrderManufacturer($order);
                break;

            case 'company':
                $assets->OrderCompany($order);
                break;

            case 'location':
                $assets->OrderLocation($order);
                break;

            case 'rtd_location':
                $assets->OrderRtdLocation($order);
                break;

            case 'status_label':
                $assets->OrderStatus($order);
                break;

            case 'supplier':
                $assets->OrderSupplier($order);
                break;

            case 'assigned_to':
                $assets->OrderAssigned($order);
                break;

            default:
                $assets->orderBy($column_sort, $order);
                break;
        }

        return $assets;
    }

    public function filters($assets, array $data, bool $is_external)
    {
        $filter = [];

        $assets->filterAssetByRole(Auth::user());

        if (Arr::exists($data, 'WAITING_CHECKOUT') || Arr::exists($data, 'WAITING_CHECKIN')) {
            $assets->where(function ($query) use ($data) {
                $query->where('assets.assigned_status', '=', $data['WAITING_CHECKOUT'])
                    ->orWhere('assets.assigned_status', '=', $data['WAITING_CHECKIN']);
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

        if (Arr::exists($data, 'location_id')) {
            $assets->where('assets.location_id', '=', $data['location_id']);
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

        if (Arr::exists($data, 'depreciation_id')) {
            $assets->ByDepreciationId($data['depreciation_id']);
        }

        if (Arr::exists($data, 'notRequest') && $data['notRequest'] == 1) {
            $assets = $assets->with('finfast_request_asset')->doesntHave('finfast_request_asset');
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

    public function handleImages($item, $data, $w = 600, $form_fieldname = null, $path = null, $db_fieldname = 'image')
    {
        $type = strtolower(class_basename(get_class($item)));

        if (is_null($path)) {
            $path = str_plural($type);

            if ($type == 'assetmodel') {
                $path = 'models';
            }

            if ($type == 'user') {
                $path = 'avatars';
            }
        }

        if (is_null($form_fieldname)) {
            $form_fieldname = 'image';
        }

        // This is dumb, but we need it for overriding field names for exceptions like avatars and logo uploads
        if (is_null($db_fieldname)) {
            $use_db_field = $form_fieldname;
        } else {
            $use_db_field = $db_fieldname;
        }


        // ConvertBase64ToFiles just changes object type, 
        // as it cannot currently insert files to $this->files
        if (
            Arr::exists($data, $form_fieldname) &&
            ($data[$form_fieldname] instanceof UploadedFile ||
                $data[$form_fieldname] instanceof HttpUploadedFile
            )
        ) {
            $image = $data[$form_fieldname];
        }


        if (isset($image)) {
            Log::debug($image);

            if (!config('app.lock_passwords')) {

                $ext = $image->getClientOriginalExtension();
                $file_name = $type . '-' . $form_fieldname . '-' . str_random(10) . '.' . $ext;

                Log::info('File name will be: ' . $file_name);
                Log::debug('File extension is: ' . $ext);

                if (
                    ($image->getClientOriginalExtension() !== 'webp') &&
                    ($image->getClientOriginalExtension() !== 'svg')
                ) {
                    Log::debug('Not an SVG or webp - resize');
                    Log::debug('Trying to upload to: ' . $path . '/' . $file_name);
                    $upload = Image::make($image->getRealPath())->resize(null, $w, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });

                    // This requires a string instead of an object, so we use ($string)
                    Storage::disk('public')->put($path . '/' . $file_name, (string) $upload->encode());
                } else {
                    // If the file is a webp, we need to just move it since webp support
                    // needs to be compiled into gd for resizing to be available
                    if ($image->getClientOriginalExtension() == 'webp') {
                        Log::debug('This is a webp, just move it');
                        Storage::disk('public')->put($path . '/' . $file_name, file_get_contents($image));
                        // If the file is an SVG, we need to clean it and NOT encode it
                    } else {
                        Log::debug('This is an SVG');
                        $sanitizer = new Sanitizer();
                        $dirtySVG = file_get_contents($image->getRealPath());
                        $cleanSVG = $sanitizer->sanitize($dirtySVG);

                        try {
                            Log::debug('Trying to upload to: ' . $path . '/' . $file_name);
                            Storage::disk('public')->put($path . '/' . $file_name, $cleanSVG);
                        } catch (\Exception $e) {
                            Log::debug('Upload no workie :( ');
                            Log::debug($e);
                        }
                    }
                }

                // Remove Current image if exists
                if (Storage::disk('public')->exists($path . '/' . $item->{$use_db_field})) {
                    Log::debug('A file already exists that we are replacing - we should delete the old one.');
                    try {
                        Storage::disk('public')->delete($path . '/' . $item->{$use_db_field});
                        Log::debug('Old file ' . $path . '/' . $file_name . ' has been deleted.');
                    } catch (\Exception $e) {
                        Log::debug('Could not delete old file. ' . $path . '/' . $file_name . ' does not exist?');
                    }
                }

                $item->{$use_db_field} = $file_name;
            }

            // If the user isn't uploading anything new but wants to delete their old image, do so
        } else {
            if (
                Arr::exists($data, 'image_delete') &&
                $data['image_delete'] == '1'
            ) {
                Log::debug('Deleting image');
                try {
                    Storage::disk('public')->delete($path . '/' . $item->{$use_db_field});
                    $item->{$use_db_field} = null;
                } catch (\Exception $e) {
                    Log::debug($e);
                }
            }
        }

        return $item;
    }

    public function getListAssets(array $data, $is_external = false)
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

    public function getTotalDetail(array $data, $is_external = false)
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

        if (Arr::exists($data, 'model_id')) {
            $asset->model()->associate(AssetModel::find((int) $data['model_id']));
        }

        $asset->name                    = $data['name'] ?? '';
        $asset->serial                  = $data['serial'] ?? '';
        $asset->model_id                = $data['model_id'] ?? '';
        $asset->order_number            = $data['order_number'] ?? '';
        $asset->notes                   = $data['notes'] ?? '';
        $asset->asset_tag               = $data['asset_tag'] ?? Asset::autoincrement_asset();
        $asset->user_id                 = Auth::id();
        $asset->archived                = '0';
        $asset->physical                = '1';
        $asset->depreciate              = '0';
        $asset->warranty_months         = $data['warranty_months'] ?? null;
        $asset->purchase_date           = $data['purchase_date'] ?? null;
        $asset->supplier_id             = $data['supplier_id'] ?? 0;
        $asset->requestable             = $data['requestable'] ?? 0;
        $asset->rtd_location_id         = $data['rtd_location_id'] ?? null;
        $asset->location_id             = $data['location_id'] ?? null;
        $asset->is_external             = $is_external;

        if (Arr::exists($data, 'status_id')) {
            $asset->status_id = $data['status_id'];
        }

        if (Arr::exists($data, 'purchase_cost')) {
            $asset->purchase_cost = Helper::ParseCurrency($data['purchase_cost']);
        }

        if (Arr::exists($data, 'company_id')) {
            $asset->company_id = Company::getIdForCurrentUser($data['company_id']);
        }

        if (Arr::exists($data, 'image_source')) {
            $data['image'] = $data['image_source'];
            $asset = $this->handleImages($asset, $data);
        }

        return $asset;
    }

    public function store(array $data, $is_external = false)
    {
        $this->asset = $this->setValueForModel($this->asset, $data, $is_external);

        if ($this->asset->save()) {
            if ($this->asset->image) {
                $this->asset->image = $this->asset->image_url;
            }
        }

        return $this->asset;
    }

    public function updateAssetStatus($asset, $data)
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
                } else {
                    $asset->checkout_counter += 1;
                    $dataPrepare['is_confirm'] = 'đã xác nhận cấp phát';
                    $asset->status_id = config('enum.status_id.ASSIGN');
                    SendConfirmMail::dispatch($dataPrepare, $it_ncc_email);
                }
            }
        }

        return $asset;
    }

    public function update(array $data, $id = null, $is_external = false)
    {
        $arr_assets_temp = [];
        $result_assets = [];
        if (!Arr::exists($data, 'assigned_status')) {
            $this->asset = Asset::find($id);

            if (!$this->asset) {
                return '404';
            }

            $this->asset = $this->setValueForModel($this->asset, $data, $is_external);
            $arr_assets_temp[] = $this->asset;
        } else {
            $assets = $data['assets'] ?? [$id];

            foreach ($assets as $asset_id) {
                $this->asset = Asset::find($asset_id);

                if (!$this->asset) {
                    return '404';
                }

                $this->asset = $this->updateAssetStatus($this->asset, $data);
                $arr_assets_temp[] = $this->asset;
            }
        }

        foreach ($arr_assets_temp as $asset) {
            if ($asset->save()) {
                if ($asset->image) {
                    $asset->image = $asset->image_url;
                }

                $result_assets[] = $asset;

                if ($asset->id === end($arr_assets_temp)->id) {
                    return $result_assets;
                }
            }
        }

        return $this->asset;
    }

    public function destroy($id)
    {
        $this->asset = Asset::find($id);

        if (!$this->asset) {
            return $this->asset;
        }

        return $this->asset->delete();
    }

    public function getAssetById($id)
    {
        return $this->asset::find($id);
    }
}
