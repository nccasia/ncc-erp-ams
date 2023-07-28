<?php

namespace App\Services;

use App\Models\Category;
use App\Models\DigitalSignatures;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\Tool;
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
        return $locations->map(function ($location) use ($categories) {
            return $this->addCategoriesToLocation($location, $categories);
        });
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
                $location['rtd_digital_signatures']
            );
        return $location;
    }

    public function mapStatusToCategory($assets, $categories, $consumables, $accessories, $tools, $digital_signatures)
    {
        $status_labels = Statuslabel::select([
            'id',
            'name',
        ])->get();
        return  $categories->map(function ($category) use ($status_labels, $assets, $consumables, $accessories, $tools, $digital_signatures) {
            return clone $this->addStatusToCategory($assets, $category, $status_labels, $consumables, $accessories, $tools, $digital_signatures);
        });
    }

    public function addStatusToCategory($assets, $category, $status_labels, $consumables, $accessories, $tools, $digital_signatures)
    {
        if ($assets->isEmpty()) {
            $category['assets_count'] = 0;
        } else {
            $assets = (new \App\Models\Asset)->scopeInCategory($assets->toQuery(), $category['id'])->get();
            $category['assets_count'] = count($assets);
        }
        if ($consumables->isEmpty()) {
            $category['consumables_count'] = 0;
        } else {
            $consumables = (new \App\Models\Consumable)->scopeInCategory($consumables->toQuery(), $category['id'])->get();
            $category['consumables_count'] = count($consumables);
        }

        if ($accessories->isEmpty()) {
            $category['accessories_count'] = 0;
        } else {
            $accessories = (new \App\Models\Accessory)->scopeInCategory($accessories->toQuery(), $category['id'])->get();
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

        $status_labels = $this->mapValueToStatusLabels($assets, $status_labels);
        $category['status_labels'] = $status_labels;

        return $category;
    }

    public function mapValueToStatusLabels($assets, $status_labels)
    {
        return $status_labels->map(function ($status_label) use ($assets) {
            return clone $this->addValueToStatusLabel($assets, $status_label);
        });
    }

    public function addValueToStatusLabel($assets, $status_label)
    {
        $assets_by_status = (new \App\Models\Asset)->getByStatusId($assets, $status_label['id']);
        $status_label['assets_count'] = count($assets_by_status->toArray());
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
                    'rtd_digital_signatures as digital_signatures_count'
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
                    }
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
            'id' => $category->id,
            'name' => $category->name,
            'status_labels' => $category->status_labels->map(function ($label) {
                return [
                    'assets_count' => $label->assets_count,
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
        foreach ($newData['status_labels'] as $label) {
            $categoryOld['status_labels'] = $categoryOld['status_labels']->map(function ($value) use ($label) {
                if ($value['id'] == $label['id']) {
                    $value['assets_count'] += $label['assets_count'];
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
        $totalData['items_count'] = 0;

        $totalData['categories'] = collect([]);
        $totalData['assets'] = [];

        foreach ($locations as $location) {
            $totalData['assets_count'] += $location->assets_count;
            $totalData['consumables_count'] += $location->consumables_count;
            $totalData['accessories_count'] += $location->accessories_count;
            $totalData['tools_count'] += $location->tools_count;
            $totalData['digital_signatures_count'] += $location->digital_signatures_count;
            $totalData['items_count'] =
                $totalData['assets_count'] +
                $totalData['consumables_count'] +
                $totalData['accessories_count'] +
                $totalData['tools_count'] +
                $totalData['digital_signatures_count'];

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

    public function queryReportAssetByType($table_name, $category_type, $location, $from, $to)
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
        $query .= ' GROUP BY ' . $table_name . '.' . $location . ', cates.name, cates.id , action_logs.action_type ) AS g
                GROUP BY g.' . $location . ', g.name , g.id ) AS g
                JOIN locations l ON l.id = g.' . $location . '';

        return $query;
    }
}
