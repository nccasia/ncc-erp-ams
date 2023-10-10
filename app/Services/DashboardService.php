<?php

namespace App\Services;

use App\Models\Category;
use App\Models\DigitalSignatures;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\Tool;
use App\Models\Asset;
use App\Models\Accessory;
use App\Models\Consumable;
use Illuminate\Support\Facades\Auth;

class DashboardService
{
    public function mapCategoryToLocation($locations)
    {

        $categories = Category::select([
            'id',
            'name',
            'category_type'
        ])->get();
        return ($locations->map(function ($location) use ($categories) {
            return $this->addCategoriesToLocation($location, $categories);
        }));
    }

    public function addCategoriesToLocation($location, $categories)
    {
        $location['categories'] =
            $this->mapStatusToCategory(
                $location['rtd_assets'],
                $categories,
                $location['rtd_consumables'],
                $location['rtd_accessories'],
                $location['rtd_tools'],
                $location['rtd_digital_signatures'],
                $location['rtd_client_assets'],
            );
        return $location;
    }

    public function mapStatusToCategory($assets, $categories, $consumables, $accessories, $tools, $digital_signatures, $client_assets)
    {
        $status_labels = Statuslabel::select([
            'id',
            'name',
        ])->get();
        return ($categories->map(function ($category) use ($status_labels, $assets, $consumables, $accessories, $tools, $digital_signatures, $client_assets) {
            return clone $this->addStatusToCategory($assets, $category, $status_labels, $consumables, $accessories, $tools, $digital_signatures, $client_assets);
        }));
    }

    public function addStatusToCategory($assets, $category, $status_labels, $consumables, $accessories, $tools, $digital_signatures, $client_assets)
    {
        if ($assets->isEmpty()) {
            $category['assets_count'] = 0;
        } else {
            $assets = (new Asset)->scopeInCategory($assets->toQuery(), $category['id'])->get();
            $category['assets_count'] = count($assets);
        }
        if ($consumables->isEmpty()) {
            $category['consumables_count'] = 0;
        } else {
            $consumables = (new Consumable)->scopeInCategory($consumables->toQuery(), $category['id'])->get();
            $category['consumables_count'] = count($consumables);
        }

        if ($accessories->isEmpty()) {
            $category['accessories_count'] = 0;
        } else {
            $accessories = (new Accessory)->scopeInCategory($accessories->toQuery(), $category['id'])->get();
            $category['accessories_count'] = count($accessories);
        }

        if ($tools->isEmpty()) {
            $category['tools_count'] = 0;
        } else {
            $tools = (new Tool)->scopeInCategory($tools->toQuery(), $category['id'])->get();
            $category['tools_count'] = count($tools);
        }

        if ($digital_signatures->isEmpty()) {
            $category['digital_signatures_count'] = 0;
        } else {
            $digital_signatures = (new DigitalSignatures)->scopeInCategory($digital_signatures->toQuery(), $category['id'])->get();
            $category['digital_signatures_count'] = count($digital_signatures);
        }

        if ($client_assets->isEmpty()) {
            $category['client_assets_count'] = 0;
        } else {
            $client_assets = (new Asset)->scopeInCategory($client_assets->toQuery(), $category['id'], true)->get();
            $category['client_assets_count'] = count($client_assets);
        }

        $status_labels = $this->mapValueToStatusLabels($assets, $consumables, $accessories, $tools, $digital_signatures, $client_assets, $status_labels);
        $category['status_labels'] = $status_labels;

        return $category;
    }

    public function mapValueToStatusLabels($assets, $consumables, $accessories, $tools, $digital_signatures, $client_assets, $status_labels)
    {
        return $status_labels->map(function ($status_label) use ($assets, $consumables, $accessories, $tools, $digital_signatures, $client_assets) {
            return clone $this->addValueToStatusLabel($assets, $consumables, $accessories, $tools, $digital_signatures, $client_assets, $status_label);
        });
    }

    public function addValueToStatusLabel($assets, $consumables, $accessories, $tools, $digital_signatures, $client_assets, $status_label)
    {
        $assets_by_status = (new Asset)->scopeByStatusId($assets, $status_label['id']);
        $consumables_by_status = (new Consumable)->scopeByStatusId($consumables, $status_label['id']);
        $accessories_by_status = (new Accessory)->scopeByStatusId($accessories, $status_label['id']);
        $tools_by_status = (new Tool)->scopeByStatusId($tools, $status_label['id']);
        $digital_signaturess_by_status = (new DigitalSignatures)->scopeByStatusId($digital_signatures, $status_label['id']);
        $client_assets_by_status = (new Asset)->scopeByStatusId($client_assets, $status_label['id'], true);


        $status_label['assets_count'] = count($assets_by_status->toArray());
        $status_label['consumables_count'] = count($consumables_by_status->toArray());
        $status_label['accessories_count'] = count($accessories_by_status->toArray());
        $status_label['tools_count'] = count($tools_by_status->toArray());
        $status_label['digital_signatures_count'] = count($digital_signaturess_by_status->toArray());
        $status_label['client_assets_count'] = count($client_assets_by_status->toArray());

        return $status_label;
    }

    public function getTotalAsset()
    {
        $counts['asset'] = \App\Models\Asset::count();
        $counts['accessory'] = \App\Models\Accessory::count();
        $counts['license'] = \App\Models\License::assetcount();
        $counts['consumable'] = \App\Models\Consumable::count();
        $counts['component'] = \App\Models\Component::count();
        $counts['user'] = \App\Models\User::count();
        $counts['grand_total'] = $counts['asset'] + $counts['accessory'] + $counts['license'] + $counts['consumable'];
    }

    public function getAllLocaltions($purchase_date_from, $purchase_date_to)
    {
        $user = Auth::user();
        if ($user->isAdmin()) {
            $locations = Location::select(['id', 'name']);
        } else {
            $manager_location = json_decode($user->manager_location, true);
            $locations = Location::select(['id', 'name'])->whereIn('id', $manager_location);
        }
        if ($purchase_date_from == null && $purchase_date_to == null) {
            $locations = $locations->withCount(
                [
                    'rtd_assets as assets_count',
                    'rtd_consumables as consumables_count',
                    'rtd_accessories as accessories_count',
                    'rtd_tools as tools_count',
                    'rtd_digital_signatures as digital_signatures_count',
                    'rtd_client_assets as client_assets_count',
                ]
            )->get();
        } else {
            $locations =  $locations->with('rtd_assets', function ($query) use ($purchase_date_from, $purchase_date_to) {
                if (!is_null($purchase_date_from)) {
                    $query = $query->where('purchase_date', '>=', $purchase_date_from);
                }
                if (!is_null($purchase_date_to)) {
                    $query = $query->where('purchase_date', '<=', $purchase_date_to);
                }
                return $query;
            })
                ->with('rtd_consumables', function ($query) use ($purchase_date_from, $purchase_date_to) {
                    if (!is_null($purchase_date_from)) {
                        $query = $query->where('purchase_date', '>=', $purchase_date_from);
                    }
                    if (!is_null($purchase_date_to)) {
                        $query = $query->where('purchase_date', '<=', $purchase_date_to);
                    }
                    return $query;
                })
                ->with('rtd_accessories', function ($query) use ($purchase_date_from, $purchase_date_to) {
                    if (!is_null($purchase_date_from)) {
                        $query = $query->where('purchase_date', '>=', $purchase_date_from);
                    }
                    if (!is_null($purchase_date_to)) {
                        $query = $query->where('purchase_date', '<=', $purchase_date_to);
                    }
                    return $query;
                })
                ->with('rtd_tools', function ($query) use ($purchase_date_from, $purchase_date_to) {
                    if (!is_null($purchase_date_from)) {
                        $query = $query->where('purchase_date', '>=', $purchase_date_from);
                    }
                    if (!is_null($purchase_date_to)) {
                        $query = $query->where('purchase_date', '<=', $purchase_date_to);
                    }
                    return $query;
                })
                ->with('rtd_digital_signatures', function ($query) use ($purchase_date_from, $purchase_date_to) {
                    if (!is_null($purchase_date_from)) {
                        $query = $query->where('purchase_date', '>=', $purchase_date_from);
                    }
                    if (!is_null($purchase_date_to)) {
                        $query = $query->where('purchase_date', '<=', $purchase_date_to);
                    }
                    return $query;
                })
                ->with('rtd_client_assets', function ($query) use ($purchase_date_from, $purchase_date_to) {
                    if (!is_null($purchase_date_from)) {
                        $query = $query->where('purchase_date', '>=', $purchase_date_from);
                    }
                    if (!is_null($purchase_date_to)) {
                        $query = $query->where('purchase_date', '<=', $purchase_date_to);
                    }
                    return $query;
                })
                ->withCount([
                    'rtd_assets as assets_count' => function ($query) use ($purchase_date_from, $purchase_date_to) {
                        if (!is_null($purchase_date_from)) {
                            $query = $query->where('purchase_date', '>=', $purchase_date_from);
                        }
                        if (!is_null($purchase_date_to)) {
                            $query = $query->where('purchase_date', '<=', $purchase_date_to);
                        }
                        return $query;
                    },
                    'rtd_consumables as consumables_count' => function ($query) use ($purchase_date_from, $purchase_date_to) {
                        if (!is_null($purchase_date_from)) {
                            $query = $query->where('purchase_date', '>=', $purchase_date_from);
                        }
                        if (!is_null($purchase_date_to)) {
                            $query = $query->where('purchase_date', '<=', $purchase_date_to);
                        }
                        return $query;
                    },
                    'rtd_accessories as accessories_count' => function ($query) use ($purchase_date_from, $purchase_date_to) {
                        if (!is_null($purchase_date_from)) {
                            $query = $query->where('purchase_date', '>=', $purchase_date_from);
                        }
                        if (!is_null($purchase_date_to)) {
                            $query = $query->where('purchase_date', '<=', $purchase_date_to);
                        }
                        return $query;
                    },
                    'rtd_tools as tools_count' => function ($query) use ($purchase_date_from, $purchase_date_to) {
                        if (!is_null($purchase_date_from)) {
                            $query = $query->where('purchase_date', '>=', $purchase_date_from);
                        }
                        if (!is_null($purchase_date_to)) {
                            $query = $query->where('purchase_date', '<=', $purchase_date_to);
                        }
                        return $query;
                    },
                    'rtd_digital_signatures as digital_signatures_count' => function ($query) use ($purchase_date_from, $purchase_date_to) {
                        if (!is_null($purchase_date_from)) {
                            $query = $query->where('purchase_date', '>=', $purchase_date_from);
                        }
                        if (!is_null($purchase_date_to)) {
                            $query = $query->where('purchase_date', '<=', $purchase_date_to);
                        }
                        return $query;
                    },
                    'rtd_client_assets as client_assets_count' => function ($query) use ($purchase_date_from, $purchase_date_to) {
                        if (!is_null($purchase_date_from)) {
                            $query = $query->where('purchase_date', '>=', $purchase_date_from);
                        }
                        if (!is_null($purchase_date_to)) {
                            $query = $query->where('purchase_date', '<=', $purchase_date_to);
                        }
                        return $query;
                    },
                ])->get();
        }
        return $locations;
    }

    private function getReportAssestByCategory($category)
    {
        $result = [
            'assets_count' => $category->assets_count,
            'consumables_count' => $category->consumables_count,
            'accessories_count' => $category->accessories_count,
            'tools_count' => $category->tools_count,
            'digital_signatures_count' => $category->digital_signatures_count,
            'category_type' => $category->category_type,
            'client_assets_count' => $category->client_assets_count,
            'id' => $category->id,
            'name' => $category->name,
            'status_labels' => $category->status_labels->map(function ($label) {
                return [
                    'assets_count' => $label->assets_count,
                    'consumables_count' => $label->consumables_count,
                    'accessories_count' => $label->accessories_count,
                    'tools_count' => $label->tools_count,
                    'digital_signatures_count' => $label->digital_signatures_count,
                    'client_assets_count' => $label->client_assets_count,
                    'id' => $label->id,
                    'name' => $label->name,
                ];
            })
        ];
        return $result;
    }

    private function increaseAssestCountForCategory(&$categoryOld, $newData)
    {
        $categoryOld['assets_count'] += $newData['assets_count'];
        $categoryOld['consumables_count'] += $newData['consumables_count'];
        $categoryOld['accessories_count'] += $newData['accessories_count'];
        $categoryOld['tools_count'] += $newData['tools_count'];
        $categoryOld['digital_signatures_count'] += $newData['digital_signatures_count'];
        $categoryOld['client_assets_count'] += $newData['client_assets_count'];

        foreach ($newData['status_labels'] as $label) {
            $categoryOld['status_labels'] = $categoryOld['status_labels']->map(function ($value) use ($label) {
                if ($value['id'] == $label['id']) {
                    $value['assets_count'] += $label['assets_count'];
                    $value['consumables_count'] += $label['consumables_count'];
                    $value['accessories_count'] += $label['accessories_count'];
                    $value['tools_count'] += $label['tools_count'];
                    $value['digital_signatures_count'] += $label['digital_signatures_count'];
                    $value['client_assets_count'] += $label['client_assets_count'];
                }
                return $value;
            });
            $labelOld = $categoryOld['status_labels']->first(function ($value) use ($label) {
                return $value['id'] == $label['id'];
            });
            if (!$labelOld) {
                $categoryOld['status_labels']->push($label);
            }
        }
    }

    public function countCategoryOfNCC($locations)
    {
        $totalData = [];
        $totalData['id'] = 99999;
        $totalData['name'] = 'TONG';
        $totalData['assets_count'] = 0;
        $totalData['consumables_count'] = 0;
        $totalData['accessories_count'] = 0;
        $totalData['tools_count'] = 0;
        $totalData['digital_signatures_count'] = 0;
        $totalData['client_assets_count'] = 0;
        $totalData['items_count'] = 0;

        $totalData['categories'] = collect([]);
        $totalData['assets'] = [];
        $totalData['client_assets'] = [];

        foreach ($locations as $location) {
            $totalData['assets_count'] += $location->assets_count;
            $totalData['consumables_count'] += $location->consumables_count;
            $totalData['accessories_count'] += $location->accessories_count;
            $totalData['tools_count'] += $location->tools_count;
            $totalData['digital_signatures_count'] += $location->digital_signatures_count;
            $totalData['client_assets_count'] += $location->client_assets_count;
            $totalData['items_count'] =
                $totalData['assets_count'] +
                $totalData['consumables_count'] +
                $totalData['accessories_count'] +
                $totalData['tools_count'] +
                $totalData['digital_signatures_count'] +
                $totalData['client_assets_count'];

            // calculate total assest of each category
            foreach ($location->categories as $category) { // loop category
                $totalData['categories'] = $totalData['categories']->map(function ($cate) use ($category) {
                    if ($cate['id'] === $category->id) {
                        $newData = $this->getReportAssestByCategory($category);
                        // reference change
                        $this->increaseAssestCountForCategory($cate, $newData);
                    }
                    return $cate;
                });

                $categoryOld = $totalData['categories']->first(function ($cate) use ($category) {
                    return $cate['id'] === $category->id;
                });
                if (!$categoryOld) {
                    $totalData['categories']->push($this->getReportAssestByCategory($category));
                }
            }
        }

        $locations[] = $totalData;

        return $locations;
    }

    public function queryReportAssetByType($table_name, $category_type, $location, $from, $to, $is_external = false)
    {
        $where = ' WHERE true ';

        if ($from && $to) {
            $where .= " AND cast(action_logs.created_at as date) >= cast(:from as date)
                                 AND cast(action_logs.created_at as date) <=  cast(:to as date)";
        }

        $query = 'SELECT g.*, l.name as location_name
            FROM
              (SELECT g.name as category_name, g.id as category_id, g.' . $location . ',
                CAST(
                sum(CASE
                    WHEN g.action_type = "checkout" THEN g.total            
                    ELSE 0
                end) AS SIGNED ) AS checkout,
                CAST(
                sum(CASE
                    WHEN g.action_type = "checkin from" THEN g.total
                    ELSE 0
                end) AS SIGNED ) AS checkin
               FROM
                 (SELECT ' . $table_name . '.' . $location . ',
                         action_logs.action_type,
                         cates.name,
                         cates.id,
                         COUNT(*) AS total
                  FROM action_logs
                  JOIN ' . $table_name . ' ON ' . $table_name . '.id = action_logs.item_id';

        if ($table_name === "assets") {
            $query .= ' JOIN models ON models.id = assets.model_id
                            JOIN categories cates ON cates.id = models.category_id';
        } else {
            $query .= ' JOIN categories cates ON cates.id = ' . $table_name . '.category_id';
        }

        $query .= $where . 'AND item_type = ' . '"' . $category_type . '"';
        if ($table_name === "assets") {
            $query .= 'AND assets.is_external = ' . '"' . $is_external . '"';
        }
        $query .= ' GROUP BY ' . $table_name . '.' . $location . ', cates.name, cates.id , action_logs.action_type ) AS g
                GROUP BY g.' . $location . ', g.name , g.id ) AS g
                JOIN locations l ON l.id = g.' . $location . '';

        return $query;
    }
}
