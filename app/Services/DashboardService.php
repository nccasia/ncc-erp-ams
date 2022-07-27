<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Location;
use App\Models\Statuslabel;

class DashboardService
{
    public function mapCategoryToLocation($locations)
    {
        $categories = Category::select(['id', 'name'])->get();
        return $locations->map(function ($location) use ($categories) {
            return $this->addCategoriesToLocation($location, $categories);
        });
    }

    public function addCategoriesToLocation($location, $categories)
    {
        $location['categories'] = !$location['assets']->isEmpty()
            ? $this->mapStatusToCategory($location['assets'], $categories)
            : [];
        return $location;
    }

    public function mapStatusToCategory($assets, $categories)
    {
        $status_labels = Statuslabel::select(['id', 'name'])->get();
        return $categories->map(function ($category) use (
            $status_labels,
            $assets
        ) {
            return clone $this->addStatusToCategory(
                $assets,
                $category,
                $status_labels
            );
        });
    }

    public function addStatusToCategory($assets, $category, $status_labels)
    {
        $assets = (new \App\Models\Asset())
            ->scopeInCategory($assets->toQuery(), $category['id'])
            ->get();
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
        $assets_by_status = (new \App\Models\Asset())->getByStatusId(
            $assets,
            $status_label['id']
        );
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
        $counts['grand_total'] =
            $counts['asset'] +
            $counts['accessory'] +
            $counts['license'] +
            $counts['consumable'];
    }

    public function getAllLocaltions(
        $purchase_date_from = null,
        $purchase_date_to = null
    ) {
        if (is_null($purchase_date_from) && is_null($purchase_date_to)) {
            $locations = Location::select(['id', 'name'])
                ->with('assets')
                ->withCount('assets as assets_count')
                ->get();
        } else {
            $locations = Location::select(['locations.id', 'locations.name'])
                ->with('assets', function ($query) use (
                    $purchase_date_from,
                    $purchase_date_to
                ) {
                    return $query
                        ->where('purchase_date', '>=', $purchase_date_from)
                        ->where('purchase_date', '<=', $purchase_date_to);
                })
                ->withCount([
                    'assets as assets_count' => function ($query) use (
                        $purchase_date_from,
                        $purchase_date_to
                    ) {
                        return $query
                            ->where('purchase_date', '>=', $purchase_date_from)
                            ->where('purchase_date', '<=', $purchase_date_to);
                    },
                ])
                ->get();
        }

        return $locations;
    }

    public function countCategoryOfNCC($locations)
    {
        $totalData = [];
        $totalData['id'] = 6;
        $totalData['name'] = 'TONG';
        $totalData['assets_count'] = 0;
        $totalData['categories'] = [];
        $totalData['assets'] = [];
        foreach ($locations as $location) {
            $totalData['assets_count'] += $location->assets_count;

            // map total categories
            $test = [];
            foreach ($location->categories as $category) {
                $index = -1;
                foreach ($totalData['categories'] as $key => $item) {
                    if ($item->id == $category->id) {
                        $index = $key;
                    }
                }
                if ($index != -1) {
                    $totalData['categories'][$index]['assets_count'] +=
                        $category->assets_count;
                    foreach ($category->status_labels as $key => $status) {
                        $totalData['categories'][$index]['status_labels'][$key][
                            'assets_count'
                        ] += $status['assets_count'];
                    }
                } else {
                    $totalData['categories'][] = clone $category;
                }
            }
        }

        $locations[] = $totalData;

        return $locations;
    }
}
