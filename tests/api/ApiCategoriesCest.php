<?php

use App\Helpers\Helper;
use App\Http\Transformers\CategoriesTransformer;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Faker\Factory;
use Illuminate\Support\Facades\Auth;

class ApiCategoriesCest
{
    protected $faker;
    protected $user;
    protected $timeFormat;

    public function _before(ApiTester $I)
    {
        $this->faker = Factory::create();
        $this->user = User::factory()->create();
        $I->haveHttpHeader('Accept', 'application/json');
        $I->amBearerAuthenticated($I->getToken($this->user));
        $this->user->permissions = json_encode(["admin" => "1"]);
        $this->user->save();
    }

    /** @test */
    public function indexCategorys(ApiTester $I)
    {
        $I->wantTo('Get a list of categories');

        // call
        $I->sendGET('/categories?order_by=id&limit=10');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse(), true);
        // sample verify
        $category = Category::withCount('assets as assets_count', 'accessories as accessories_count', 'consumables as consumables_count', 'components as components_count', 'licenses as licenses_count')
            ->orderByDesc('created_at')->take(10)->get()->shuffle()->first();
        $I->seeResponseContainsJson($I->removeTimestamps((new CategoriesTransformer)->transformCategory($category)));
    }

    /** @test */
    public function createCategory(ApiTester $I, $scenario)
    {
        $I->wantTo('Create a new category');
        // setup
        $data = [
            'category_type' => 'asset',
            'checkin_email' => $this->faker->numberBetween(0,1),
            'eula_text' => $this->faker->paragraph(),
            'name' => $this->faker->name(),
            'require_acceptance' => $this->faker->numberBetween(0,1),
            'use_default_eula' =>  $this->faker->numberBetween(0,1),
        ];
        // create
        $I->sendPOST('/categories', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    // Put is routed to the same method in the controller
    // DO we actually need to test both?

    /** @test */
    public function updateCategoryWithPatch(ApiTester $I, $scenario)
    {
        $I->wantTo('Update an category with PATCH');

        // create
        $category = Category::factory()
            ->create([
                'name' => $this->faker->name(),
                'category_type' => 'accessory',
                'require_acceptance' => $this->faker->numberBetween(0,1),
                'checkin_email' => $this->faker->boolean(),
                'use_default_eula' => $this->faker->numberBetween(0,1),
        ]);
        $I->assertInstanceOf(Category::class, $category);

        $temp_category = Category::factory()->make([
            'name' => 'Updated Category name',
            'category_type' => 'asset',
            'require_acceptance' => $this->faker->numberBetween(0,1),
            'checkin_email' => $this->faker->boolean(),
            'use_default_eula' => $this->faker->numberBetween(0,1),
        ]);

        $data = [
            'category_type' => $temp_category->category_type,
            'checkin_email' => $temp_category->checkin_email,
            'eula_text' => $temp_category->eula_text,
            'name' => $temp_category->name,
            'require_acceptance' => $temp_category->require_acceptance,
            'use_default_eula' => $temp_category->use_default_eula,
        ];

        $I->assertNotEquals($category->name, $data['name']);

        // update
        $I->sendPATCH('/categories/'.$category->id, $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());

        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/categories/message.update.success'), $response->messages);
        $I->assertEquals($category->id, $response->payload->id); // category id does not change
        $I->assertEquals($temp_category->name, $response->payload->name); // category name updated
        // Some manual copying to compare against
        $temp_category->created_at = Carbon::parse($response->payload->created_at);
        $temp_category->updated_at = Carbon::parse($response->payload->updated_at);
        $temp_category->id = $category->id;

        // verify
        $I->sendGET('/categories/'.$category->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson((new CategoriesTransformer)->transformCategory($temp_category));
    }

    /** @test */
    public function deleteCategoryTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Delete an category');

        // create
        $category = Category::factory()->create([
            'name' => $this->faker->name(),
            'category_type' => 'asset',
            'require_acceptance' => $this->faker->numberBetween(0,1),
            'checkin_email' => $this->faker->boolean(),
            'use_default_eula' => $this->faker->numberBetween(0,1),
        ]);
        $I->assertInstanceOf(Category::class, $category);

        // delete
        $I->sendDELETE('/categories/'.$category->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/categories/message.delete.success'), $response->messages);

        // verify, expect a 200
        $I->sendGET('/categories/'.$category->id);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }
}
