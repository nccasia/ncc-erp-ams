<?php

use App\Http\Transformers\UsersTransformer;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Department;
use App\Models\Group;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ApiUsersCest
{
    protected $user;
    protected $timeFormat;

    public function _before(ApiTester $I)
    {
        $this->user = User::factory()->create();
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Content-type', 'application/json');
        $I->amBearerAuthenticated($I->getToken($this->user));
    }

    /** @test */
    public function indexUsers(ApiTester $I)
    {
        $I->wantTo('Get a list of users');

        $filter = '?limit=10&sort=created_at'
            . '&dateFrom=' . Carbon::now()
            . '&dateTo=' . Carbon::now()->addDays(5)
            . '&location_id=' . Location::all()->random(1)->first()->id
            . '&company_id=' . Company::all()->random(1)->first()->id
            . '&email=' . 'Dick'
            . '&username=' . 'admin'
            . '&first_name=' . 'Grayson'
            . '&last_name=' . 'Dick'
            . '&employee_num=' . '123123'
            . '&search=' . 'admin'
            . '&assets_count=' . rand(1,9)
            . '&consumables_count=' . rand(1,9)
            . '&licenses_count=' . rand(1,9)
            . '&accessories_count=' . rand(1,9)
            . '&activated=' . '1'
            . '&all=' . 'true'
            . '&state=' . 'DC'
            . '&country=' . 'USA'
            . '&zip=' . '123'
            . '&manager_id=' . User::all()->random(1)->first()->id
            . '&department_id=' . Department::all()->random(1)->first()->id;

        // call
        $I->sendGET('/users' . $filter);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    /** @test */
    public function createUser(ApiTester $I, $scenario)
    {
        $I->wantTo('Create a new user');

        $company = Company::factory()->create();
        $department = Department::factory()->create();
        $location = Location::factory()->create();
        $manager = User::factory()->create();
        $temp_user = User::factory()->make([
            'name' => 'Test User Name',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'location_id' => $location->id,
            'manager_id' => $manager->id,
            'permissions' => '{"assets.view":"1","admin":"1","branchadmin":"1","superuser":"1"}',
            'manager_location' => $location->id
        ]);
        Group::factory()->count(2)->create();
        $groups = Group::pluck('id')->toArray();
        // setup
        $data = [
            'activated' => $temp_user->activated,
            'address' => $temp_user->address,
            'city' => $temp_user->city,
            'company_id' => $temp_user->company_id,
            'country' => $temp_user->country,
            'department_id' => $temp_user->department_id,
            'email' => $temp_user->email,
            'employee_num' => $temp_user->employee_num,
            'first_name' => $temp_user->first_name,
            'jobtitle' => $temp_user->jobtitle,
            'last_name' => $temp_user->last_name,
            'locale' => $temp_user->locale,
            'location_id' => $temp_user->location_id,
            'notes' => $temp_user->notes,
            'manager_id' => $temp_user->manager_id,
            'password' => $temp_user->password,
            'password_confirmation' => $temp_user->password,
            'phone' => $temp_user->phone,
            'state' => $temp_user->state,
            'username' => $temp_user->username,
            'zip' => $temp_user->zip,
            'groups' => $groups,
            'permissions' => $temp_user->permissions,
            'manager_location' => $temp_user->manager_location
        ];

        // create
        $I->sendPOST('/users', $data);
        $I->seeResponseIsJson();
        $user = User::where('username', $temp_user->username)->first();
        $I->assertEquals($groups, $user->groups()->pluck('id')->toArray());
        $I->seeResponseCodeIs(200);
    }

    /** @test */
    public function updateUserWithPatch(ApiTester $I, $scenario)
    {
        $I->wantTo('Update an user with PATCH');

        // create
        $user = User::factory()->create([
            'first_name' => 'Original User Name',
        ]);
        $I->assertInstanceOf(User::class, $user);

        $company = Company::factory()->create();
        $department = Department::factory()->create();
        $location = Location::factory()->create();
        $manager = User::factory()->create();
        $temp_user = User::factory()->make([
            'name' => 'Test User Name',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'location_id' => $location->id,
            'manager_id' => $manager->id,
            'permissions' => '{"assets.view":"1","admin":"1","branchadmin":"1","superuser":"1"}',
            'manager_location' => $location->id
        ]);

        Group::factory()->count(2)->create();
        $groups = Group::pluck('id')->toArray();

        $data = [
            'activated' => $temp_user->activated,
            'address' => $temp_user->address,
            'city' => $temp_user->city,
            'company_id' => $temp_user->company_id,
            'country' => $temp_user->country,
            'department_id' => $temp_user->department_id,
            'email' => $temp_user->email,
            'employee_num' => $temp_user->employee_num,
            'first_name' => $temp_user->first_name,
            'groups' => $groups,
            'jobtitle' => $temp_user->jobtitle,
            'last_name' => $temp_user->last_name,
            'locale' => $temp_user->locale,
            'location_id' => $temp_user->location_id,
            'notes' => $temp_user->notes,
            'manager_id' => $temp_user->manager_id,
            'password' => $temp_user->password,
            'phone' => $temp_user->phone,
            'state' => $temp_user->state,
            'username' => $temp_user->username,
            'zip' => $temp_user->zip,
            'permissions' => $temp_user->permissions,
            'manager_location' => $temp_user->manager_location
        ];

        $I->assertNotEquals($user->first_name, $data['first_name']);

        // update
        $I->sendPATCH('/users/' . $user->id, $data);
        $I->seeResponseIsJson();

        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/users/message.success.update'), $response->messages);
        $I->assertEquals($user->id, $response->payload->id); // user id does not change
        $I->assertEquals($temp_user->company_id, $response->payload->company->id); // company_id updated
        $I->assertEquals($temp_user->first_name, $response->payload->first_name); // user name updated
        $I->assertEquals($temp_user->location_id, $response->payload->location->id); // user location_id updated
        $newUser = User::where('username', $temp_user->username)->first();
        $I->assertEquals($groups, $newUser->groups()->pluck('id')->toArray());
        $temp_user->created_at = Carbon::parse($response->payload->created_at->datetime);
        $temp_user->updated_at = Carbon::parse($response->payload->updated_at->datetime);
        $temp_user->id = $user->id;
        // verify
        $I->sendGET('/users/'.$user->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson((new UsersTransformer)->transformUser($temp_user));
    }

    public function updateUserAsBranchManager(ApiTester $I, $scenario)
    {
        $I->wantTo('Update an user as branch manager');

        // create
        $user = User::factory()->create([
            'first_name' => 'Original User Name',
        ]);
        $I->assertInstanceOf(User::class, $user);

        $data = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'password' => $user->password,
            'username' => $user->username,
            'permissions' => '{"assets.view":"1","admin":"1","branchadmin":"1","superuser":"1"}',
        ];
        // update
        $I->sendPATCH('/users/' . $user->id, $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals(trans('admin/users/message.manager_location'), $response->messages->manager_location);
    }

    public function updateUserAsUserManager(ApiTester $I, $scenario)
    {
        $I->wantTo('Update an user as his own manager');

        // create
        $user = User::factory()->create([
            'first_name' => 'Original User Name',
        ]);
        $I->assertInstanceOf(User::class, $user);

        $data = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'password' => $user->password,
            'username' => $user->username,
            'manager_id' => $user->id
        ];
        // update
        $I->sendPATCH('/users/' . $user->id, $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals('You cannot be your own manager', $response->messages);
    }

    /** @test */
    public function deleteUserTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Delete an user');

        // create
        $user = User::factory()->create([
            'first_name' => 'Soon to be deleted',
        ]);
        $I->assertInstanceOf(User::class, $user);

        // delete
        $I->sendDELETE('/users/' . $user->id);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/users/message.success.delete'), $response->messages);
    }

    public function deleteUserFailTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Delete an user with assigned item');

        $userHasAssets = User::factory()->create();
        Asset::factory()->laptopAir()->create([
            'assigned_to' => $userHasAssets->id,
            'assigned_type' => 'App\Models\User'
        ]);
        $I->sendDELETE('/users/' . $userHasAssets->id);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals(trans('admin/users/message.error.delete_has_assets'), $response->messages);

        $userHasAccessories = User::factory()->create();
        $accessory = Accessory::factory()->appleBtKeyboard()->create();
        DB::table('accessories_users')->insertGetId([
            'user_id' => $this->user->id,
            'accessory_id' => $accessory->id,
            'assigned_to' => $userHasAccessories->id,
        ]);
        $I->sendDELETE('/users/' . $userHasAccessories->id);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals('This user still has '.$userHasAccessories->accessories->count().' accessories associated with them.', $response->messages);

        $userHasLicense = User::factory()->create();
        $license = License::factory()->acrobat()->create();
        DB::table('license_seats')->insertGetId([
            'user_id' => $this->user->id,
            'license_id' => $license->id,
            'assigned_to' => $userHasLicense->id,
        ]);
        $I->sendDELETE('/users/' . $userHasLicense->id);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals('This user still has '.$userHasLicense->licenses->count().' license(s) associated with them and cannot be deleted.', $response->messages);
    }

    /** @test */
    public function fetchUserAssetsTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Fetch assets for a user');

        $user = User::factory()->create();
        $model = AssetModel::factory()->mbp13Model()->create();
        $asset = Asset::factory()->create([
            'assigned_to' => $user->id,
            'assigned_type' => User::class,
            'model_id' => $model->id
        ]);

        $I->sendGET("/users/{$user->id}/assets");
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        // Just test a random one.
        $I->seeResponseContainsJson([
            'asset_tag' => $asset->asset_tag,
        ]);
    }

    public function fetchUserLicensesTest(ApiTester $I)
    {
        $I->wantTo('Fetch licenses for a user');

        $user = User::factory()->create();
        $license = License::factory()->acrobat()->create();
        DB::table('license_seats')->insertGetId([
            'user_id' => $this->user->id,
            'license_id' => $license->id,
            'assigned_to' => $user->id,
        ]);

        $I->sendGET("/users/{$user->id}/licenses");
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'id' => $license->id,
            'name' => $license->name
        ]);
    }

    public function fetchUserAccessoriesTest(ApiTester $I)
    {
        $I->wantTo('Fetch accessories for a user');

        $user = User::factory()->create();
        $accessory = Accessory::factory()->appleBtKeyboard()->create();
        $accessory->assigned_to = $user->id;

        $accessory->users()->attach($accessory->id, [
            'accessory_id' => $accessory->id,
            'created_at' => Carbon::now(),
            'user_id' => Auth::id(),
            'assigned_to' => $user->id,
            'note' => 'Test getting accessories from user',
        ]);

        $I->sendGET("/users/{$user->id}/accessories");
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'id' => $accessory->id,
            'name' => $accessory->name
        ]);
    }

    public function postTwoFactorResetTest(ApiTester $I)
    {
        $I->wantTo('Test two factor reset');

        $user = User::factory()->create();

        $I->sendPost('users/two_factor_reset',);
        $response = json_decode($I->grabResponse());
        $I->seeResponseCodeIs(500);
        $I->assertEquals('No ID provided', $response->message);

        $I->sendPost('users/two_factor_reset', [
            'id' => $user->id
        ]);
        $response = json_decode($I->grabResponse());
        $I->seeResponseCodeIs(200);
        $I->assertEquals(trans('admin/settings/general.two_factor_reset_success'), $response->message);
    }

    public function getCurrentUserInfoTest(ApiTester $I)
    {
        $I->wantTo('Test getting current user info');

        $I->sendGet('users/me');
        $response = json_decode($I->grabResponse());
        $I->seeResponseCodeIs(200);
        $I->assertEquals($this->user->id, $response->id);
    }

    public function restoreTest(ApiTester $I)
    {
        $I->wantTo('Test resrore user');

        $user = User::factory()->create();
        $user->delete();

        $I->sendPost('users/' . $user->id . '/restore');
        $response = json_decode($I->grabResponse());
        $I->seeResponseCodeIs(200);
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/users/message.success.restored'), $response->messages);
    }

    public function indexSelectedUser(ApiTester $I)
    {
        $I->wantTo('Get a list of users');

        $location = Location::all()->random(1)->first()->id;
        $user = User::factory()->create([
            'first_name' => 'Dick',
            'last_name' => 'Grayson',
            'show_in_list' => 1,
            'location_id' => $location,
            'employee_num' => rand(1, 99),
            'username' => 'testing'
        ]);

        $filter = '?limit=10&sort=created_at'
            . '&location_id=' . $location
            . '&search=' . 'Dick';
        // call
        $I->sendGET('/users/selectlist' . $filter);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'id' => $user->id
        ]);
    }

    public function loginTest(ApiTester $I)
    {
        $I->wantTo('Test user login');
        $user = User::factory()->create([
            'username' => 'testing',
            'password' => Hash::make('password')
        ]);

        $I->sendPost('auth/login', [
            'username' => 'testing',
            'password' => 'wrongpassword'
        ]);
        $response = json_decode($I->grabResponse());
        $I->seeResponseCodeIs(401);
        $I->assertEquals('Thông tin đăng nhập không chính xác', $response->message);

        $I->sendPost('auth/login', [
            'username' => 'testing',
            'password' => 'password'
        ]);
        $response = json_decode($I->grabResponse());
        $I->seeResponseCodeIs(200);
        $I->assertEquals('Bear', $response->token_type);
    }
}
