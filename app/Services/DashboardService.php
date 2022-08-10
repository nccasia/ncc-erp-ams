<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Location;
use App\Models\Statuslabel;

class DashboardService
{
    public function mapCategoryToLocation($locations)
    {

        $categories = Category::select([
            'id',
            'name',
        ])->get();
        return $locations->map(function ($location) use ($categories) {
            return $this->addCategoriesToLocation($location, $categories);
        });
    }

    public function addCategoriesToLocation($location, $categories)
    {
        $location['categories'] = !$location['rtd_assets']->isEmpty() ? $this->mapStatusToCategory($location['rtd_assets'], $categories) : [];
        return $location;
    }

    public function mapStatusToCategory($assets, $categories)
    {
        $status_labels = Statuslabel::select([
            'id',
            'name',
        ])->get();
        return  $categories->map(function ($category) use ($status_labels, $assets) {
            return clone $this->addStatusToCategory($assets, $category, $status_labels);
        });
    }

    public function addStatusToCategory($assets, $category, $status_labels)
    {
        $assets = (new \App\Models\Asset)->scopeInCategory($assets->toQuery(), $category['id'])->get();
        $category['assets_count'] = count($assets);

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
        $locations = Location::select(['id', 'name']);
        if ($purchase_date_from == null && $purchase_date_to == null) {
            $locations = $locations->with('rtd_assets')->withCount('rtd_assets as assets_count')->get();
        } else {
            $locations =  $locations->with('rtd_assets', function($query) use($purchase_date_from, $purchase_date_to) {
                if (!is_null($purchase_date_from)) {
                    $query = $query->where('purchase_date', '>=', $purchase_date_from);
                }
                if (!is_null($purchase_date_to)) {
                    $query = $query->where('purchase_date', '<=', $purchase_date_to);
                }
                return $query;
            })
            ->withCount(['rtd_assets as assets_count' => function($query) use($purchase_date_from, $purchase_date_to) {
                    if (!is_null($purchase_date_from)) {
                        $query = $query->where('purchase_date', '>=', $purchase_date_from);
                    }
                    if (!is_null($purchase_date_to)) {
                        $query = $query->where('purchase_date', '<=', $purchase_date_to);
                    }
                    return $query;
                }])->get();
        }

        return $locations;
    }
    private function getReportAssestByCategory($category) {
        $result = [
                    'assets_count' => $category->assets_count,
                    'id' => $category->id,
                    'name' => $category->name,
                    'status_labels' => $category->status_labels->map(function($label) {
                        return [
                            'assets_count' => $label->assets_count,
                            'id' => $label->id,
                            'name' => $label->name,
                        ];
                    })
                ];
        return $result;
    }

    private function increaseAssestCountForCategory(&$categoryOld, $newData) {
        $categoryOld['assets_count'] += $newData['assets_count'];
        foreach ($newData['status_labels'] as $label) {
            $categoryOld['status_labels'] = $categoryOld['status_labels']->map(function($value) use($label) {
                if ($value['id'] == $label['id']) {
                    $value['assets_count'] += $label['assets_count'];
                }
                return $value;
            });
            $labelOld = $categoryOld['status_labels']->first(function($value) use($label) {
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
        $totalData['categories'] = collect([]);
        $totalData['assets'] = [];
        
        foreach($locations as $location) {
            $totalData['assets_count'] += $location->assets_count;
            // calculate total assest of each category
            foreach ($location->categories as $category) {// loop category
                $totalData['categories'] = $totalData['categories']->map(function ($cate) use ($category){
                    if ($cate['id'] === $category->id) {
                        $newData = $this->getReportAssestByCategory($category);
                        // reference change
                        $this->increaseAssestCountForCategory($cate, $newData);
                    }
                    return $cate;
                });

                $categoryOld= $totalData['categories']->first(function ($cate) use ($category){
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
}
