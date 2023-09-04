<?php

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Software;
use App\Models\User;
use Faker\Factory;

class ApiSoftwareCest
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

    // tests
    public function indexSoftware(ApiTester $I)
    {
        $I->wantTo("Get software data for index ");
        $manufacturer = Manufacturer::factory()->create([
            'name' => $this->faker->name(),
        ]);
        $software = Software::factory()->create([
            'manufacturer_id' => $manufacturer->id,
        ]);
        $I->sendGET("/software", [
            'filter' => '{}',
            'search' => 'k',
            'dateFrom' => '2023-08-01',
            'dateTo' => '2023-08-10',
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $I->sendGET("/software", [
            'filter' => json_encode(['manufacturer_id' => $manufacturer->id]),
            'limit' => 2,
            'offset' => 2,
            'manufacturer_id' => $manufacturer->id,
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    protected function sort($I, $column, $order)
    {
        $I->sendGET('/software', [
            'sort' => $column,
            'order' => $order,
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function sortSoftwareByColumn(ApiTester $I)
    {
        $I->wantTo("Get list software sort by column");
        $this->sort($I, 'category', 'asc');
        $this->sort($I, 'manufacturer', 'asc');
        $this->sort($I, 'checkout_count', 'asc');
    }

    public function createASoftware(ApiTester $I)
    {
        $I->wantTo("Create A new Software");
        $category = Category::factory()->assetDesktopCategory()->create([
            'name' => $this->faker->name(),
            'category_type' => 'tool',
        ]);
        $manufacturer = Manufacturer::factory()->create([
            'name' => $this->faker->name(),
        ]);

        $data = [
            'name' => $this->faker->name(),
            'software_tag' => $this->faker->text(5),
            'category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
            'version' => '2.0',
        ];

        //success
        $I->sendPOST("software", $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/softwares/message.create.success'), $response->messages);
        $software = Software::where('name', '=', $data['name'])->first();
        $I->assertNotNull($software);

        //error
        $data['name'] = null;
        $I->sendPOST("software", $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals('The name field is required.', $response->messages->name[0]);
    }

    public function showSoftware(ApiTester $I)
    {
        $manufacturer = Manufacturer::factory()->create([
            'name' => $this->faker->name(),
        ]);
        $software = Software::factory()->create([
            'manufacturer_id' => $manufacturer->id,
        ]);
        $I->wantTo("Get A Software Details");
        $I->sendGET("/software/{$software->id}");
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function updateSoftware(ApiTester $I)
    {
        $I->wantTo("Update A Software");
        $category = Category::factory()->assetDesktopCategory()->create([
            'name' => $this->faker->name(),
            'category_type' => 'tool',
        ]);
        $manufacturer = Manufacturer::factory()->create([
            'name' => $this->faker->name(),
        ]);
        $software = Software::factory()->create([
            'manufacturer_id' => $manufacturer->id,
        ]);

        $data_update = [
            'name' => $this->faker->name(),
            'software_tag' => $this->faker->text(5),
            'category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
            'version' => '3.0',
        ];

        //Update Successfully
        $I->sendPATCH("/software/{$software->id}", $data_update);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse());
        $software = Software::find($software->id);
        $I->assertEquals($software['name'], $response->payload->name);
        $I->assertEquals($software['software_tag'], $response->payload->software_tag);
        
        //update error
        $data_update['name'] = null;
        $I->sendPATCH("/software/{$software->id}", $data_update);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals('The name field is required.', $response->messages->name[0]);
        
        //update not found
        $id_not_found = $software->id + 1;
        $I->sendPATCH("/software/{$id_not_found}");
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals(trans('admin/softwares/message.does_not_exist'), $response->messages);
    }

    public function deleteSoftware(ApiTester $I)
    {
        $I->wantTo("Delete A Software");
        
        //init
        $category = Category::factory()->assetDesktopCategory()->create([
            'name' => $this->faker->name(),
            'category_type' => 'tool',
        ]);
        $manufacturer = Manufacturer::factory()->create([
            'name' => $this->faker->name(),
        ]);
        $software = Software::factory()->create([
            'manufacturer_id' => $manufacturer->id,
            'category_id' => $category->id,
        ]);
        $I->sendDELETE("/software/{$software->id}");
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/softwares/message.delete.success'), $response->messages);
    }
}
