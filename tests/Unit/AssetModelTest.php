<?php
namespace Tests\Unit;

use App\Models\Asset;
use App\Models\Category;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\Supplier;
use Tests\Unit\BaseTest;

class AssetModelTest extends BaseTest
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function testAnAssetModelZerosOutBlankEols()
    {
        $am = new AssetModel;
        $am->eol = '';
        $this->assertTrue($am->eol === 0);
        $am->eol = '4';
        $this->assertTrue($am->eol == 4);
    }

    public function testAnAssetModelContainsAssets()
    {
        $category = Category::factory()->create(
            ['category_type' => 'asset']
        );
        $status_label = Statuslabel::factory()->readyToDeploy()->create();
        $supplier = Supplier::factory()->create();
        $location = Location::factory()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $category->id,
        ]);
        $asset = Asset::factory()
            ->create(
                [
                    'model_id' => $model->id,
                    'status_id' => $status_label->id,
                    'supplier_id' => $supplier->id,
                    'rtd_location_id' => $location->id,
                    'assigned_status' => 1
                ]
            );
        $this->assertEquals(1, $model->assets()->count());
    }


}
