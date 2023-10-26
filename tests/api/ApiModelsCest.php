<?php

use App\Http\Transformers\AssetModelsTransformer;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Depreciation;
use App\Models\Manufacturer;
use App\Models\User;
use Faker\Factory;

class ApiModelsCest
{
    protected $user;
    protected $timeFormat;
    protected $faker;

    public function _before(ApiTester $I)
    {
        $this->user = User::factory()->create();
        $this->faker = Factory::create();
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
        $response = json_decode($I->grabResponse());
        $I->assertEquals($data['name'], $response->payload->name);
        $I->assertEquals($category->id, $response->payload->category_id);
        $I->assertEquals($manufacture->id, $response->payload->manufacturer_id);

        //create error
        $data['name'] = null;
        $I->sendPOST('/models', $data);
        $I->seeResponseCodeIs(400);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals('The name field is required.', $response->messages->name[0]);
    }

    /** @test */
    public function updateAssetModelWithPatch(ApiTester $I, $scenario)
    {
        $I->wantTo('Update an assetmodel with PATCH');

        // create
        $assetmodel = AssetModel::factory()->mbp13Model()->create([
            'name' => $this->faker->name(),
        ]);
        $I->assertInstanceOf(AssetModel::class, $assetmodel);

        $category = Category::factory()->create(['category_type' => 'accessory']);
        $depreciation = Depreciation::factory()->create();
        $manufacture = Manufacturer::factory()->create();
        $temp_assetmodel = AssetModel::factory()->mbp13Model()->make([
            'name' => $this->faker->name(),
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

        // update success
        $I->sendPATCH('/models/'.$assetmodel->id, $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());

        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/models/message.update.success'), $response->messages);
        $I->assertEquals($assetmodel->id, $response->payload->id); // assetmodel id does not change
        $I->assertEquals($temp_assetmodel->name, $response->payload->name); // assetmodel name updated

        // Some necessary manual copying
        $temp_assetmodel->created_at = $response->payload->created_at;
        $temp_assetmodel->updated_at = $response->payload->updated_at;
        $temp_assetmodel->id = $assetmodel->id;
        // verify
        $I->sendGET('/models/'.$assetmodel->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson((new AssetModelsTransformer)->transformAssetModel($temp_assetmodel));

        //update error
        $data['name'] = null;
        $I->sendPATCH('/models/'.$assetmodel->id, $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(400);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals('The name field is required.', $response->messages->name[0]);
    }

    /** @test */
    public function deleteAssetModelTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Delete an assetmodel');

        // create
        $assetmodel = AssetModel::factory()->mbp13Model()->create([
            'name' => $this->faker->name(),
        ]);
        $I->assertInstanceOf(AssetModel::class, $assetmodel);

        // delete
        $I->sendDELETE('/models/'.$assetmodel->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/models/message.delete.success'), $response->messages);

        //delete error
        $assetmodel_error = AssetModel::factory()->mbp13Model()->create([
            'name' => $this->faker->name(),
        ]);
        $asset = Asset::factory()->create([
            'model_id' => $assetmodel_error->id,
        ]);
        $I->sendDELETE('/models/'.$assetmodel_error->id);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals(trans('admin/models/message.assoc_users'), $response->messages);
    }

    public function selectAssetModelsList(ApiTester $I)
    {
        $I->wantTo('Get a list of asset models');

        $I->sendGET("/models/selectlist", [
            'search' => 'to',
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }
}
