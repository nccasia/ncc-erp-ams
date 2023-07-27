<?php

use App\Http\Transformers\AssetModelsTransformer;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Depreciation;
use App\Models\Manufacturer;
use App\Models\User;

class ApiModelsCest
{
    protected $user;
    protected $timeFormat;

    public function _before(ApiTester $I)
    {
        $this->user = User::factory()->create();
        $I->haveHttpHeader('Accept', 'application/json');
        $I->amBearerAuthenticated($I->getToken($this->user));
    }

    /** @test */
    public function indexAssetModels(ApiTester $I)
    {
        $I->wantTo('Get a list of assetmodels');

        // call
        $I->sendGET('/models?limit=10');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $assetmodel = AssetModel::orderByDesc('created_at')
            ->withCount('assets as assets_count')->take(10)->get()->shuffle()->first();
        $I->seeResponseContainsJson($I->removeTimestamps((new AssetModelsTransformer)->transformAssetModel($assetmodel)));
    }

    /** @test */
    public function createAssetModel(ApiTester $I, $scenario)
    {
        $I->wantTo('Create a new assetmodel');

        $category = Category::factory()->create(['category_type' => 'accessory']);
        $depreciation = Depreciation::factory()->create();
        $manufacture = Manufacturer::factory()->create();
        $temp_assetmodel = AssetModel::factory()->mbp13Model()->make([
            'name' => 'Test AssetModel Tag',
            'category_id' => $category->id,
            'depreciation_id' => $depreciation->id,
            'manufacturer_id' => $manufacture->id
        ]);

        // setup
        $data = [
            'category_id' => $temp_assetmodel->category_id,
            'depreciation_id' => $temp_assetmodel->depreciation_id,
            'eol' => $temp_assetmodel->eol,
            'manufacturer_id' => $temp_assetmodel->manufacturer_id,
            'model_number' => $temp_assetmodel->model_number,
            'name' => $temp_assetmodel->name,
            'notes' => $temp_assetmodel->notes,
            'requestable' => 1
        ];

        // create
        $I->sendPOST('/models', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    /** @test */
    public function updateAssetModelWithPatch(ApiTester $I, $scenario)
    {
        $I->wantTo('Update an assetmodel with PATCH');

        // create
        $assetmodel = AssetModel::factory()->mbp13Model()->create([
            'name' => 'Original AssetModel Name',
        ]);
        $I->assertInstanceOf(AssetModel::class, $assetmodel);

        $category = Category::factory()->create(['category_type' => 'accessory']);
        $depreciation = Depreciation::factory()->create();
        $manufacture = Manufacturer::factory()->create();
        $temp_assetmodel = AssetModel::factory()->mbp13Model()->make([
            'name' => 'Test AssetModel Tag',
            'category_id' => $category->id,
            'depreciation_id' => $depreciation->id,
            'manufacturer_id' => $manufacture->id
        ]);;

        $data = [
            'category_id' => $temp_assetmodel->category_id,
            'depreciation_id' => $temp_assetmodel->depreciation_id,
            'eol' => $temp_assetmodel->eol,
            'manufacturer_id' => $temp_assetmodel->manufacturer_id,
            'model_number' => $temp_assetmodel->model_number,
            'name' => $temp_assetmodel->name,
            'notes' => $temp_assetmodel->notes,
            'fieldset' => $temp_assetmodel->fieldset->id,
        ];

        $I->assertNotEquals($assetmodel->name, $data['name']);

        // update
        $I->sendPATCH('/models/'.$assetmodel->id, $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());

        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/models/message.update.success'), $response->messages);
        $I->assertEquals($assetmodel->id, $response->payload->id); // assetmodel id does not change
        $I->assertEquals($temp_assetmodel->name, $response->payload->name); // assetmodel name updated

        // Some necessary manual copying
        $temp_assetmodel->created_at = Carbon::parse($response->payload->created_at);
        $temp_assetmodel->updated_at = Carbon::parse($response->payload->updated_at);
        $temp_assetmodel->id = $assetmodel->id;
        // verify
        $I->sendGET('/models/'.$assetmodel->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson((new AssetModelsTransformer)->transformAssetModel($temp_assetmodel));
    }

    /** @test */
    public function deleteAssetModelTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Delete an assetmodel');

        // create
        $assetmodel = AssetModel::factory()->mbp13Model()->create([
            'name' => 'Soon to be deleted',
        ]);
        $I->assertInstanceOf(AssetModel::class, $assetmodel);

        // delete
        $I->sendDELETE('/models/'.$assetmodel->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/models/message.delete.success'), $response->messages);

        // verify, expect a 200
        $I->sendGET('/models/'.$assetmodel->id);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }
}
