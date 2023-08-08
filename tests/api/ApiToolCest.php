<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Tool;
use App\Models\User;
use Faker\Factory;

class ApiToolCest
{
    protected $faker;
    protected $user;
    protected $timeFormat;
    public function _before(ApiTester $I)
    {
        $this->faker = Factory::create();
        $this->user = User::factory()->create();
        Setting::getSettings()->time_display_format = 'H:i';
        $I->amBearerAuthenticated($I->getToken($this->user));
        $I->haveHttpHeader('Content-Type', 'application/json');
    }

    protected function createATool($assigned_status = 0, $assigned_to = null, $assigned_type = null, $status_id = 5, $withdraw_from = null, )
    {
        return
            Tool::factory()->create([
                'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get())->format('Y-m-d H:i:s'),
                'expiration_date' => $this->faker->dateTimeBetween('+1 days', '+30 years' ,date_default_timezone_get())->format('Y-m-d H:i:s'),
                'assigned_status' => $assigned_status,
                'assigned_to' => $assigned_to,
                'assigned_type' => $assigned_type,
                'status_id' => $status_id,
                'withdraw_from' => $withdraw_from,
            ]);
    }

    // tests
    public function indexTools(ApiTester $I)
    {
        $I->wantTo('Get a list of tools');
        // call
        $I->sendGET('/tools?limit=10&offset=0&order=desc&sort=id');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function getToolWaitingToConfirm(ApiTester $I)
    {
        $I->wantTo('Get Tools Are Waiting to Confirm');
        $I->sendGET('/tools?limit=10&offset=0&order=desc&sort=id', [
            "WAITING_CHECKIN" => 5,
            "WAITING_CHECKOUT" => 4,
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    protected function sort($I, $column, $order)
    {
        $I->sendGET('/tools?limit=10&offset=0', [
            'sort' => $column,
            'order' => $order,
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }
    public function sortToolByColumn(ApiTester $I)
    {
        $I->wantTo('Sort Tool By Column');
        
        //Sort by assigned to
        $this->sort($I, 'assigned_to', 'desc');

        //Sort By supplier
        $this->sort($I, 'supplier', 'asc');

        //Sort By location
        $this->sort($I, 'location', 'desc');

        //Sort By Category
        $this->sort($I, 'category', 'asc');
    }

    protected function filter($I, $filterName, $value)
    {
        $I->sendGET('/tools?limit=10&offset=0', [
            $filterName => $value,
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }
    protected function filterByDate($I, $from, $to, $value)
    {
        $I->sendGET('/tools?limit=10&offset=0', [
            $from => $value[0],
            $to => $value[1],
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }
    public function filterTool(ApiTester $I)
    {
        $I->wantTo("Filter Tool");

        //Filter By Status_label
        $this->filter($I, 'status_label', config("enum.status_id.ASSIGN"));
            
        //Filter By Supplier_id
        $this->filter($I, 'supplier_id', Supplier::factory()->create()->id);

        //Filter By assigned_status
        $this->filter($I, 'assigned_status', [config("enum.assigned_status.WAITINGCHECKOUT"), config("enum.assigned_status.WAITINGCHECKIN")]);

        //search
        $this->filter($I, 'search', '12');

        //filter by purchase date
        $this->filterByDate($I, 'purchaseDateFrom', 'purchaseDateTo', ['2023-08-08', '2023-09-06']);

        //filter by expire date
        $this->filterByDate($I, 'expirationDateFrom', 'expirationDateTo', ['2023-08-09', '2023-08-21']);
    }

    public function createTool(ApiTester $I)
    {
        $I->wantTo('Create a new Tool');
        
        //set up data
        $data =  [
            'name' => $this->faker->name(),
            'supplier_id' => Supplier::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'assigned_status' => 0,
            'assigned_to' => null,
            'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get())->format('Y-m-d H:i:s'),
            'expiration_date' => $this->faker->dateTimeBetween('+1 days', '+30 years' ,date_default_timezone_get())->format('Y-m-d H:i:s'),
            'purchase_cost' => $this->faker->randomFloat(2, '299.99', '2999.99'),
            'notes'   => 'Created by DB seeder',
            'status_id' => 5,
            'category_id' => Category::where('category_type', '=', 'tool')->inRandomOrder()->first()->id,
            'qty' => $this->faker->numberBetween(5, 10),
            'location_id' => Location::factory()->create()->id,
        ];

        //send request
        $I->sendPOST('/tools', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }
    public function updateToolWithPut(ApiTester $I)
    {
        $I->wantTo('Update a tool with PUT');

        // create
        $tool = $this->createATool();
        $I->assertInstanceOf(Tool::class, $tool);

        $data = [
            'name' => 'Updated Tool name',
            'supplier_id' => Supplier::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'assigned_status' => 0,
            'assigned_to' => null,
            'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get())->format('Y-m-d H:i:s'),
            'expiration_date' => $this->faker->dateTimeBetween('+1 days', '+30 years' ,date_default_timezone_get())->format('Y-m-d H:i:s'),
            'purchase_cost' => $this->faker->randomFloat(2, '299.99', '2999.99'),
            'notes'   => 'Created by DB seeder',
            'status_id' => 5,
            'category_id' => Category::where('category_type', '=', 'tool')->inRandomOrder()->first()->id,
            'qty' => $this->faker->numberBetween(5, 10),
            'location_id' => Location::factory()->create()->id,
        ];
        

        $response = $I->sendPut('/tools/'.$tool->id, $data);

        $response = json_decode($response);
        $I->assertEquals('Updated Tool name', $response->payload->name);
        $I->assertEquals($tool->id, $response->payload->id);
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

    }
    public function deleteTool(ApiTester $I)
    {
        $I->wantTo('Delete a tool');
        // create
        $tool = $this->createATool();
        $I->assertInstanceOf(Tool::class, $tool);

        $I->sendDELETE('/tools/'.$tool->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/tools/message.delete.success'), $response->messages);
    }

    public function checkoutTool(ApiTester $I)
    {
        $I->wantTo('checkout tool');
        
        //prepare data
        $toolPrepareCheckout = $this->createATool();

        $user = User::factory()->create();

        $toolCheckedout = $this->createATool(0, $user->id);

        //Scenario: tool checked out before
        $I->sendPost("/tools/{$toolCheckedout->id}/checkout",[
            "assigned_to" => $user->id,
            "checkout_at" => "2023-08-07T15:10",
            "note" => "",
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.checkout.not_available'), $response->messages);
        $I->assertNull($response->payload);

        //Scenario: checkout
        $I->sendPost("/tools/{$toolPrepareCheckout->id}/checkout",[
            "assigned_to" => $user->id,
            "checkout_at" => "2023-08-07T15:10",
            "note" => "",
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.checkout.success'), $response->messages);
        $I->assertEquals('success', $response->status);
    }

    public function multiCheckoutTool(ApiTester $I)
    {
        $user = User::factory()->create();
        $I->wantTo('checkout multiple tool');

        $tool1 = $this->createATool();
        $tool2 = $this->createATool();

        $I->sendPOST('/tools/multicheckout', [
            'assigned_to' => $user->id,
            "checkout_at" => "2023-08-08T11:38",
            "note" => "",
            "tools" => [$tool1->id, $tool2->id],
        ]);

        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.checkout.success'), $response->messages);
        $I->assertEquals('success', $response->status);
    }

    public function confirmCheckoutTool(ApiTester $I)
    {
        $user = User::factory()->create();
        $I->wantTo('confirm checkout tool');

        //Accept checkout
        $toolCheckedoutApprove = $this->createATool(config("enum.assigned_status.WAITINGCHECKOUT"),$user->id,'App\Models\User');       

        $I->sendPUT("/tools/{$toolCheckedoutApprove->id}", [
            'assigned_status' => config("enum.assigned_status.ACCEPT"),
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        $I->assertNotEquals($toolCheckedoutApprove->assigned_status, $response->payload->assigned_status);

        //Decline checkout
        $toolCheckedoutDecline = $this->createATool(config("enum.assigned_status.WAITINGCHECKOUT"),$user->id,'App\Models\User');          

        $I->sendPUT("/tools/{$toolCheckedoutDecline->id}", [
            'reason' => $this->faker->text(),
            'assigned_status' => config("enum.assigned_status.REJECT"),
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        $I->assertNotEquals($toolCheckedoutDecline->assigned_status, $response->payload->assigned_status);
    }

    public function multipleConfirmCheckout(ApiTester $I)
    {
        $user = User::factory()->create();
        $I->wantTo('confirm multi checkout tool');

        //Approve
        $toolCheckedoutApprove1 = $this->createATool(config("enum.assigned_status.WAITINGCHECKOUT"),$user->id,'App\Models\User');
        $toolCheckedoutApprove2 = $this->createATool(config("enum.assigned_status.WAITINGCHECKOUT"),$user->id,'App\Models\User');
        $I->sendPUT("/tools", [
            'assigned_status' => config("enum.assigned_status.ACCEPT"),
            'tools' => [$toolCheckedoutApprove1->id, $toolCheckedoutApprove2->id],
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        
        //Decline
        $toolCheckedoutDecline1 = $this->createATool(config("enum.assigned_status.WAITINGCHECKOUT"),$user->id,'App\Models\User');
        $toolCheckedoutDecline2 = $this->createATool(config("enum.assigned_status.WAITINGCHECKOUT"),$user->id,'App\Models\User');
        $I->sendPUT("/tools", [
            'assigned_status' => config("enum.assigned_status.REJECT"),
            'tools' => [$toolCheckedoutDecline1->id, $toolCheckedoutDecline2->id],
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
    }

    public function checkinTool(ApiTester $I)
    {
        $I->wantTo('checkin tool');
        $user = User::factory()->create();

        //Invalid Checkin
        $toolInvalidCheckin = $this->createATool(config("enum.assigned_status.WAITINGCHECKOUT"),$user->id,'App\Models\User',config("enum.status_id.READY_TO_DEPLOY"),$user->id);       
        $I->sendPost("/tools/{$toolInvalidCheckin->id}/checkin");
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.checkin.not_available'), $response->messages);
        $I->assertEquals('error', $response->status);

        //Valid Checkin
        $toolValidCheckin = $this->createATool(config("enum.assigned_status.ACCEPT"),$user->id,'App\Models\User',config("enum.status_id.ASSIGN"),$user->id);
        $I->sendPost("/tools/{$toolValidCheckin->id}/checkin");
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.checkin.success'), $response->messages);
        $I->assertEquals('success', $response->status);
    }

    public function multipleCheckinTool(ApiTester $I)
    {
        $I->wantTo('checkin multi tool');
        $user = User::factory()->create();

        $toolCheckin1 = $this->createATool(config("enum.assigned_status.ACCEPT"),$user->id,'App\Models\User',config("enum.status_id.ASSIGN"),$user->id);
        $toolCheckin2 = $this->createATool(config("enum.assigned_status.ACCEPT"),$user->id,'App\Models\User',config("enum.status_id.ASSIGN"),$user->id);
        
        $I->sendPOST("tools/multicheckin", [
            'assigned_user' => [$user->id, $user->id],
            'checkin_at' => "2023-08-08T13:17",
            'note' => '',
            'tools' => [$toolCheckin1->id, $toolCheckin2->id],
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.checkin.success'), $response->messages);
        $I->assertEquals('success', $response->status);
    }

    public function confirmCheckin(ApiTester $I)
    {
        $I->wantTo('Confirm Check In Tool');

        $user = User::factory()->create();

        //Accept Checkin
        $toolAcceptCheckin = $this->createATool(config("enum.assigned_status.WAITINGCHECKIN"),$user->id,'App\Models\User',config("enum.status_id.ASSIGN"),$user->id);
        $I->sendPUT("/tools/{$toolAcceptCheckin->id}", ['assigned_status' => 2,]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        $I->assertNotEquals($toolAcceptCheckin->assigned_status, $response->payload->assigned_status);

        //Decline checkin
        $toolDeclineCheckin = $this->createATool(config("enum.assigned_status.WAITINGCHECKIN"),$user->id,'App\Models\User',config("enum.status_id.ASSIGN"),$user->id);
        $I->sendPUT("/tools/{$toolDeclineCheckin->id}",[
            'reason' => $this->faker->text(),
            'assigned_status' => config("enum.assigned_status.REJECT"),
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
    }

    public function multipleConfirmCheckin(ApiTester $I)
    {
        $I->wantTo('Confirm Multiple Check In Tool');

        $user = User::factory()->create();
        //Accept Checkin
        $toolAcceptCheckin1 = $this->createATool(config("enum.assigned_status.WAITINGCHECKIN"),$user->id,'App\Models\User',config("enum.status_id.ASSIGN"),$user->id);
        $toolAcceptCheckin2 = $this->createATool(config("enum.assigned_status.WAITINGCHECKIN"),$user->id,'App\Models\User',config("enum.status_id.ASSIGN"),$user->id);
        $I->sendPUT("/tools",[
            'assigned_status' => config("enum.assigned_status.ACCEPT"),
            'tools' => [$toolAcceptCheckin1->id, $toolAcceptCheckin2->id],
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        
        //Decline multiple tools
        $toolDeclineCheckin1 = $this->createATool(config("enum.assigned_status.WAITINGCHECKIN"),$user->id,'App\Models\User',config("enum.status_id.ASSIGN"),$user->id);
        $toolDeclineCheckin2 = $this->createATool(config("enum.assigned_status.WAITINGCHECKIN"),$user->id,'App\Models\User',config("enum.status_id.ASSIGN"),$user->id);
        $I->sendPUT("/tools",[
            'assigned_status' => config("enum.assigned_status.REJECT"),
            'tools' => [$toolDeclineCheckin1->id, $toolDeclineCheckin2->id],
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
    }
}
