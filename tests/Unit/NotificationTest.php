<?php
namespace Tests\Unit;

use App\Models\Location;
use App\Models\User;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Statuslabel;
use App\Models\Supplier;
use Carbon\Carbon;
use App\Notifications\CheckoutAssetNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Unit\BaseTest;


class NotificationTest extends BaseTest
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function testAUserIsEmailedIfTheyCheckoutAnAssetWithEULA()
    {

        $user = User::factory()->create([
            'location_id' => Location::factory()->create()->id,
        ]);
        $admin = User::factory()->create();
        $status_label = Statuslabel::factory()->readyToDeploy()->create();
        $supplier = Supplier::factory()->create();
        $location = Location::factory()->create();
        $model = AssetModel::factory()->create(
            [
                'category_id' => Category::factory()->create(
                    [
                        'name' => 'test for category',
                        'category_type' => 'asset',
                    ]
                )->id
            ]
        );
        
        $asset = Asset::factory()
        ->create(
            [
                'model_id' => $model->id,
                'status_id' => $status_label->id,
                'supplier_id' => $supplier->id,
                'warranty_months' => 24,
                'rtd_location_id' => $location->id,
                'purchase_date' =>   Carbon::createFromDate(2017, 1, 1)->hour(0)->minute(0)->second(0),
                'assigned_status' => 1,
                'withdraw_from' => null,
            ]);

        Notification::fake();
        $asset->checkOut($user,$admin);
        Notification::assertSentTo($user, CheckoutAssetNotification::class);
    }
}
