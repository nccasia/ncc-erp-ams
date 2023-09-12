<?php

use App\Http\Transformers\AssetHistoriesTransformer;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory;

class ApiAssetReportCest
{
    protected $faker;
    protected $user;
    public function _before(ApiTester $I)
    {
        $this->faker = Factory::create();
        $this->user = User::factory()->create();
        $I->haveHttpHeader('Accept', 'application/json');
        $I->amBearerAuthenticated($I->getToken($this->user));
    }

    public function getAssetHistory(ApiTester $I)
    {
        $status_label = Statuslabel::factory()->readyToDeploy()->create();
        $supplier = Supplier::factory()->create();
        $location = Location::factory()->create();
        $category = Category::factory()->create([
                        'name' => 'test for category',
                        'category_type' => 'asset',
                    ]);
        $model = AssetModel::factory()->create([
                    'category_id' => $category->id
                ]);
        $asset = Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $status_label->id,
            'supplier_id' => $supplier->id,
            'warranty_months' => 24,
            'rtd_location_id' => $location->id,
            'purchase_date' =>   Carbon::createFromDate(2017, 1, 1)->hour(0)->minute(0)->second(0),
            'assigned_status' => 1,
            'withdraw_from' => null,
        ]);

        $actionLog1 = Actionlog::create([
            'user_id' => $this->user->id,
            'action_type' => config("enum.log_status.CHECKOUT_ACCEPTED"),
            'item_type' => Asset::class,
            'item_id' => $asset->id,
            'log_meta' => '{"assigned_to":{"old":'. $this->user->id .',"new":'. $this->user->id .'},"assigned_status":{"old":4,"new":2},"checkout_counter":{"old":0,"new":1},"withdraw_from":{"old":null,"new":null}}',
        ]);
        $actionLog1->user = $this->user;

        $actionLog2 = Actionlog::create([
            'user_id' => $this->user->id,
            'action_type' => config("enum.log_status.CHECKIN_ACCEPTED"),
            'item_type' => Asset::class,
            'item_id' => $asset->id,
            'log_meta' => '{"assigned_to":{"old":' . $this->user->id . ',"new":null},"status_id":{"old":4,"new":5},"assigned_status":{"old":5,"new":0},"checkin_counter":{"old":0,"new":1},"withdraw_from":{"old":' . $this->user->id . ',"new":null}}',
        ]);
        $actionLog2->user = $this->user;

        $another_user = User::factory()->create([
            'location_id' => Location::factory()->create()->id,
        ]);
        $actionLog3 = Actionlog::create([
            'user_id' => $another_user->id,
            'action_type' => config("enum.log_status.CHECKOUT_ACCEPTED"),
            'item_type' => Asset::class,
            'item_id' => $asset->id,
            'log_meta' => '{"assigned_to":{"old":' . $another_user->id . ',"new":' . $another_user->id . '},"assigned_status":{"old":4,"new":2},"checkout_counter":{"old":1,"new":2},"withdraw_from":{"old":null,"new":null}}',
        ]);
        $actionLog3->user = $another_user;

        //send request
        $I->sendGET("/hardware/{$asset->id}/report");
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $temp_history = [
            $actionLog3,
            $actionLog2,
            $actionLog1,
        ];
        $I->seeResponseContainsJson((new AssetHistoriesTransformer)->transformAssetHistories(collect($temp_history)));
    }
}
