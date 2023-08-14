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
            "WAITING_CHECKIN" => config("enum.assigned_status.WAITINGCHECKIN"),
            "WAITING_CHECKOUT" => config("enum.assigned_status.WAITINGCHECKOUT"),
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $assignedStatus = [
            config("enum.assigned_status.DEFAULT"),
            config("enum.assigned_status.WAITING"),
            config("enum.assigned_status.ACCEPT"),
            config("enum.assigned_status.REJECT")
        ];
        foreach ($assignedStatus as $status) {
            $stringToCheck = '"assigned_status:"' . $status;
            $I->assertStringNotContainsString($stringToCheck, $I->grabResponse());
        }

        $response = json_decode($I->grabResponse());
        if($response->total) {
            $stringToCheckWaitingCheckin = '"assigned_status:"' . config("enum.assigned_status.WAITINGCHECKIN");
            $stringToCheckWaitingCheckout = '"assigned_status:"' . config("enum.assigned_status.WAITINGCHECKOUT");
            $I->assertStringContainsString(
                $stringToCheckWaitingCheckin || $stringToCheckWaitingCheckout,
                $I->grabResponse()
            );
        }
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
        
        $response = json_decode($I->grabResponse());
        $I->assertEquals($data['name'], $response->payload->name);
        $I->assertEquals($data['purchase_cost'], $response->payload->purchase_cost);
        $I->assertEquals(trans('admin/tools/message.create.success'), $response->messages);
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
        

        $I->sendPut('/tools/'.$tool->id, $data);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('Updated Tool name', $response->payload->name);
        $I->assertEquals($tool->id, $response->payload->id);
        $I->assertEquals($data['purchase_cost'], $response->payload->purchase_cost);
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
        $I->assertNull($response->payload);
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
        $I->assertEquals($toolPrepareCheckout->name, $response->payload->tool);
        //verify can checkout
        $toolPrepareCheckout = Tool::find($toolPrepareCheckout->id);
        $I->assertEquals($user->id, $toolPrepareCheckout->assigned_to);
    }

    public function multiCheckoutTool(ApiTester $I)
    {
        $I->wantTo('checkout multiple tool');

        $user = User::factory()->create();
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
        $I->assertNull($response->payload);
        
        //verify
        $tool1 = Tool::find($tool1->id);
        $tool2 = Tool::find($tool2->id);
        $I->assertEquals($user->id, $tool1->assigned_to);
        $I->assertEquals($user->id, $tool2->assigned_to);
    }

    public function confirmCheckoutTool(ApiTester $I)
    {
        $user = User::factory()->create();
        $I->wantTo('confirm checkout tool');

        //Accept checkout
        $toolCheckedoutApprove = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKOUT"),
            $user->id,
            'App\Models\User'
        );       

        $I->sendPUT("/tools/{$toolCheckedoutApprove->id}", [
            'assigned_status' => config("enum.assigned_status.ACCEPT"),
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        $I->assertEquals(config("enum.assigned_status.ACCEPT"), $response->payload->assigned_status);
        $I->assertEquals($toolCheckedoutApprove->id, $response->payload->id);

        //Decline checkout
        $toolCheckedoutDecline = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKOUT"),
            $user->id,
            'App\Models\User'
        );

        $I->sendPUT("/tools/{$toolCheckedoutDecline->id}", [
            'reason' => $this->faker->text(),
            'assigned_status' => config("enum.assigned_status.REJECT"),
        ]);

        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        $I->assertEquals(config("enum.assigned_status.DEFAULT"), $response->payload->assigned_status);

        //verify decline checkout tool
        $toolCheckedoutDecline = Tool::find($toolCheckedoutDecline->id);
        $I->assertNull($toolCheckedoutDecline->assigned_to);
    }

    public function multipleConfirmCheckout(ApiTester $I)
    {
        $user = User::factory()->create();
        $I->wantTo('confirm multi checkout tool');

        //Approve
        $toolCheckedoutApprove1 = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKOUT"),
            $user->id,
            'App\Models\User'
        );
        $toolCheckedoutApprove2 = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKOUT"),
            $user->id,
            'App\Models\User'
        );

        $I->sendPUT("/tools", [
            'assigned_status' => config("enum.assigned_status.ACCEPT"),
            'tools' => [$toolCheckedoutApprove1->id, $toolCheckedoutApprove2->id],
        ]);

        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        $I->assertStringContainsString(
            $response->payload->name, 
            $toolCheckedoutApprove1->name . $toolCheckedoutApprove2->name
        );
        
        //Decline
        $toolCheckedoutDecline1 = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKOUT"),
            $user->id,
            'App\Models\User'
        );
        $toolCheckedoutDecline2 = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKOUT"),
            $user->id,
            'App\Models\User'
        );

        $I->sendPUT("/tools", [
            'assigned_status' => config("enum.assigned_status.REJECT"),
            'tools' => [$toolCheckedoutDecline1->id, $toolCheckedoutDecline2->id],
        ]);

        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        $I->assertStringContainsString(
            $response->payload->name,
            $toolCheckedoutDecline1->name . $toolCheckedoutDecline2->name
        );

        //verify decline checkout tools
        $toolCheckedoutDecline1 = Tool::find($toolCheckedoutDecline1->id);
        $I->assertNull($toolCheckedoutDecline1->assigned_to);
        $toolCheckedoutDecline2 = Tool::find($toolCheckedoutDecline2->id);
        $I->assertNull($toolCheckedoutDecline2->assigned_to);
    }

    public function checkinTool(ApiTester $I)
    {
        $I->wantTo('checkin tool');
        $user = User::factory()->create();

        //Invalid Checkin
        $toolInvalidCheckin = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKOUT"),
            $user->id,
            'App\Models\User',
            config("enum.status_id.READY_TO_DEPLOY"),
            $user->id
        );
        $I->sendPost("/tools/{$toolInvalidCheckin->id}/checkin");
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.checkin.not_available'), $response->messages);
        $I->assertEquals('error', $response->status);
        $I->assertEquals($toolInvalidCheckin->name, $response->payload->tool);

        //Valid Checkin
        $toolValidCheckin = $this->createATool(
            config("enum.assigned_status.ACCEPT"),
            $user->id,
            'App\Models\User',
            config("enum.status_id.ASSIGN"),
            $user->id
        );
        $I->sendPost("/tools/{$toolValidCheckin->id}/checkin");
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.checkin.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        $I->assertEquals($toolValidCheckin->name, $response->payload->tool);
    }

    public function multipleCheckinTool(ApiTester $I)
    {
        $I->wantTo('checkin multi tool');
        $user = User::factory()->create();

        $toolCheckin1 = $this->createATool(
            config("enum.assigned_status.ACCEPT"),
            $user->id,
            'App\Models\User',
            config("enum.status_id.ASSIGN"),
            $user->id
        );
        $toolCheckin2 = $this->createATool(
            config("enum.assigned_status.ACCEPT"),
            $user->id,
            'App\Models\User',
            config("enum.status_id.ASSIGN"),
            $user->id
        );
        
        $I->sendPOST("tools/multicheckin", [
            'assigned_user' => [$user->id, $user->id],
            'checkin_at' => "2023-08-08T13:17",
            'note' => '',
            'tools' => [$toolCheckin1->id, $toolCheckin2->id],
        ]);

        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.checkin.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        $I->assertNull($response->payload);
    }

    public function confirmCheckin(ApiTester $I)
    {
        $I->wantTo('Confirm Check In Tool');

        $user = User::factory()->create();

        //Accept Checkin
        $toolAcceptCheckin = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKIN"),
            $user->id,
            'App\Models\User',
            config("enum.status_id.ASSIGN"),
            $user->id
        );
        $I->sendPUT("/tools/{$toolAcceptCheckin->id}", ['assigned_status' => 2,]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        $I->assertEquals(config("enum.assigned_status.DEFAULT"), $response->payload->assigned_status);
        
        //verify accept checkin
        $toolAcceptCheckin = Tool::find($toolAcceptCheckin->id);
        $I->assertNull($toolAcceptCheckin->assigned_to);

        //Decline checkin
        $toolDeclineCheckin = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKIN"),
            $user->id,
            'App\Models\User',
            config("enum.status_id.ASSIGN"),
            $user->id
        );
        $I->sendPUT("/tools/{$toolDeclineCheckin->id}",[
            'reason' => $this->faker->text(),
            'assigned_status' => config("enum.assigned_status.REJECT"),
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        $I->assertEquals(config("enum.assigned_status.ACCEPT"), $response->payload->assigned_status);

        //verify decline checkin
        $toolDeclineCheckin = Tool::find($toolDeclineCheckin->id);
        $I->assertNotNull($toolDeclineCheckin->assigned_to);
    }

    public function multipleConfirmCheckin(ApiTester $I)
    {
        $I->wantTo('Confirm Multiple Check In Tool');

        $user = User::factory()->create();
        //Accept Checkin
        $toolAcceptCheckin1 = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKIN"),
            $user->id,'App\Models\User',
            config("enum.status_id.ASSIGN"),
            $user->id
        );
        $toolAcceptCheckin2 = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKIN"),
            $user->id,
            'App\Models\User',
            config("enum.status_id.ASSIGN"),
            $user->id
        );
        
        $I->sendPUT("/tools",[
            'assigned_status' => config("enum.assigned_status.ACCEPT"),
            'tools' => [$toolAcceptCheckin1->id, $toolAcceptCheckin2->id],
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);
        
        //verify approve success
        $toolAcceptCheckin1 = Tool::find($toolAcceptCheckin1->id);
        $toolAcceptCheckin2 = Tool::find($toolAcceptCheckin2->id);
        $I->assertNull($toolAcceptCheckin1->assigned_to);
        $I->assertNull($toolAcceptCheckin2->assigned_to);
        
        //Decline multiple tools
        $toolDeclineCheckin1 = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKIN"),
            $user->id,
            'App\Models\User',
            config("enum.status_id.ASSIGN"),
            $user->id
        );
        $toolDeclineCheckin2 = $this->createATool(
            config("enum.assigned_status.WAITINGCHECKIN"),
            $user->id,
            'App\Models\User',
            config("enum.status_id.ASSIGN"),
            $user->id
        );

        $I->sendPUT("/tools",[
            'assigned_status' => config("enum.assigned_status.REJECT"),
            'tools' => [$toolDeclineCheckin1->id, $toolDeclineCheckin2->id],
        ]);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(trans('admin/tools/message.update.success'), $response->messages);
        $I->assertEquals('success', $response->status);

        //verify decline checkin
        $toolDeclineCheckin1 = Tool::find($toolDeclineCheckin1->id);
        $toolDeclineCheckin2 = Tool::find($toolDeclineCheckin2->id);
        $I->assertNotNull($toolDeclineCheckin2->assigned_to);
        $I->assertNotNull($toolDeclineCheckin2->assigned_to);
    }
}
