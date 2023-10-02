<?php

use App\Http\Transformers\DigitalSignaturesTransformer;
use App\Models\Category;
use App\Models\DigitalSignatures;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Faker\Factory;
use Carbon\Carbon;

class ApiDigitalSignaturesCest
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
    }

    protected function testSendPost($I, $link, $status, $messages, $data)
    {
        $I->sendPost($link, $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals($status, $response->status);
        $I->assertEquals($messages, $response->messages);
    }

    protected function digitalSignatureFactory(
        $qty = 0,
        $assigned_status = 0,
        $status_id = 5,
        $assigned_to = null,
        $assigned_type = null,
        $withdraw_from = null
    ) {
        $category = Category::factory()->create(['category_type' => 'taxtoken']);
        $location = Location::factory()->create();
        $digital_signatures = DigitalSignatures::factory();
        if ($qty > 0) $digital_signatures = DigitalSignatures::factory()->count($qty);
        $params_array = [
            'name' => 'Test checkout',
            'qty' => $this->faker->numberBetween(5, 10),
            'location_id' => $location->id,
            'category_id' => $category->id,
            'assigned_status' => $assigned_status,
            'status_id' => $status_id
        ];
        if ($assigned_to) $params_array['assigned_to'] = $assigned_to;
        if ($assigned_type) $params_array['assigned_type'] = $assigned_type;
        if ($withdraw_from) $params_array['withdraw_from'] = $withdraw_from;
        $digital_signatures = $digital_signatures->create($params_array);
        return $digital_signatures;
    }

    public function indexDigitalSingatures(ApiTester $I)
    {
        $I->wantTo('Get a list of digital signatures');

        // call
        $filter = '?limit=10&offset=0&order=desc&sort=id'
            . '&assigned_status[0]=' . config('enum.assigned_status.DEFAULT')
            . '&status_label[0]=' . config('enum.status_id.READY_TO_DEPLOY')
            . '&purchaseDateFrom=' . Carbon::now()->subDays(5)
            . '&purchaseDateTo=' . Carbon::now()->addDays(5)
            . '&expirationDateFrom=' . Carbon::now()->subMonths(2)
            . '&expirationDateTo=' . Carbon::now()->addMonths(2)
            . '&supplier=' . Supplier::all()->random(1)->first()->id
            . '&search=' . 'Token';
        $I->sendGET('/digital_signatures' . $filter);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function totalDetailDigitalSingatures(ApiTester $I)
    {
        $I->wantTo('Get a list of digital signatures');

        // call
        $filter = '?limit=10&offset=0&order=desc&sort=id'
            . '&assigned_status[0]=' . config('enum.assigned_status.DEFAULT')
            . '&status_label[0]=' . config('enum.status_id.READY_TO_DEPLOY')
            . '&purchaseDateFrom=' . Carbon::now()->subDays(5)
            . '&purchaseDateTo=' . Carbon::now()->addDays(5)
            . '&expirationDateFrom=' . Carbon::now()->subMonths(2)
            . '&expirationDateTo=' . Carbon::now()->addMonths(2)
            . '&supplier=' . Supplier::all()->random(1)->first()->id
            . '&search=' . 'Token';
        $I->sendGET('/digital_signatures/total-detail' . $filter);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
    }

    public function getDigitalSignatureById(ApiTester $I)
    {
        $I->wantTo('Get digital signature by id');

        $digital_signature = DigitalSignatures::factory()->create();

        $I->sendGet('/digital_signatures/' . $digital_signature->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'id' => $digital_signature->id,
            'name' => $digital_signature->name
        ]);
    }

    public function createDigitalSignature(ApiTester $I, $scenario)
    {
        $I->wantTo('Create a new digital signature');
        $category = Category::factory()->create(['category_type' => 'accessory']);
        $location = Location::factory()->create();
        // setup
        $data = [
            'name' => $this->faker->name(),
            'seri' =>  $this->faker->uuid,
            'supplier_id' => Supplier::all()->random()->id,
            'user_id' => 1,
            'assigned_status' => 0,
            'assigned_to' => null,
            'purchase_date' => $this->faker->dateTimeBetween('-1 years', 'now', date_default_timezone_get())->format('Y-m-d H:i:s'),
            'expiration_date' => $this->faker->dateTimeBetween('now', 'now', date_default_timezone_get())->format('Y-m-d H:i:s'),
            'purchase_cost' => $this->faker->randomFloat(2, '299.99', '2999.99'),
            'note'   => 'Created by DB seeder',
            'status_id' => 5,
            'category_id' => $category->id,
            'qty' => $this->faker->numberBetween(5, 10),
            'location_id' => $location->id,
            'warranty_months' => $this->faker->numberBetween(5, 10)
        ];

        // create
        $I->sendPOST('/digital_signatures', $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'name' => $data['name'],
            'seri' => $data['seri']
        ]);
    }

    public function updateDigitalSignatureWithPatch(ApiTester $I, $scenario)
    {
        $I->wantTo('Update a digital signature with PATCH');

        // create
        $digital_signature = $this->digitalSignatureFactory();
        $I->assertInstanceOf(DigitalSignatures::class, $digital_signature);
        $temp = $this->digitalSignatureFactory();
        $data = [
            'name' => $temp->name,
            'supplier_id' => $temp->supplier_id,
            'user_id' => $temp->user_id,
            'assigned_status' => $temp->assigned_status,
            'assigned_to' => $temp->assigned_to,
            'purchase_date' => $temp->purchase_date->format('Y-m-d H:i:s'),
            'expiration_date' => $temp->expiration_date->format('Y-m-d H:i:s'),
            'purchase_cost' => $temp->purchase_cost,
            'note'   => $temp->note,
            'status_id' => $temp->status_id,
            'category_id' => $temp->category_id,
            'qty' => $temp->qty,
            'location_id' => $temp->location_id,
            'warranty_months' => $temp->warranty_months
        ];

        // update
        $I->sendPut('/digital_signatures/' . $digital_signature->id, $data);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());

        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/digital_signatures/message.update.success'), $response->messages);
        $I->assertEquals($digital_signature->id, $response->payload->id);
        $I->assertEquals($temp->name, $response->payload->name);
    }

    public function digitalSignaturesMultipleConfirmCheckout(ApiTester $I)
    {
        $I->wantTo('Digital signatures confirm multiple checkout');

        $link = 'digital_signatures';
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => Location::factory()->create()->id
        ]);

        $digital_signatures_accept_checkout = $this->digitalSignatureFactory(
            2,
            config('enum.assigned_status.WAITINGCHECKOUT'),
            config('enum.status_id.ASSIGN'),
            $user->id,
            'App\Models\User',
            $user->id
        );
        $data_accept_checkout = [
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'tax_tokens' => $digital_signatures_accept_checkout->pluck('id')->toArray(),
            '_method' => 'PUT'
        ];
        $messages = trans('admin/digital_signatures/message.update.success');
        $I->sendPost($link, $data_accept_checkout);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals($messages, $response->messages);

        $digital_signatures_reject_checkout = $this->digitalSignatureFactory(
            2,
            config('enum.assigned_status.WAITINGCHECKOUT'),
            config('enum.status_id.ASSIGN'),
            $user->id,
            'App\Models\User',
            $user->id
        );
        $data_reject_checkout = [
            'assigned_status' => config('enum.assigned_status.REJECT'),
            'tax_tokens' => $digital_signatures_reject_checkout->pluck('id')->toArray(),
            '_method' => 'PUT'
        ];
        $messages =  trans('admin/digital_signatures/message.update.success');
        $I->sendPost($link, $data_reject_checkout);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals($messages, $response->messages);
    }

    public function digitalSignaturesMultipleConfirmCheckin(ApiTester $I)
    {
        $I->wantTo('digital signatures confirm multiple checkin');

        $link = 'digital_signatures';
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => Location::factory()->create()->id
        ]);
        $digital_signatures_accept_checkin = $this->digitalSignatureFactory(
            2,
            config('enum.assigned_status.WAITINGCHECKIN'),
            config('enum.status_id.ASSIGN'),
            $user->id,
            'App\Models\User',
            null
        );
        $data_accept_checkin = [
            'assigned_status' => config('enum.assigned_status.ACCEPT'),
            'tax_tokens' => $digital_signatures_accept_checkin->pluck('id')->toArray(),
            '_method' => 'PUT'
        ];
        $messages =  trans('admin/digital_signatures/message.update.success');
        $I->sendPost($link, $data_accept_checkin);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals($messages, $response->messages);

        $digital_signatures_reject_checkin = $this->digitalSignatureFactory(
            2,
            config('enum.assigned_status.WAITINGCHECKIN'),
            config('enum.status_id.ASSIGN'),
            $user->id,
            'App\Models\User',
            null
        );
        $data_reject_checkin = [
            'assigned_status' => config('enum.assigned_status.REJECT'),
            'tax_tokens' => $digital_signatures_reject_checkin->pluck('id')->toArray(),
            '_method' => 'PUT'
        ];
        $messages =  trans('admin/digital_signatures/message.update.success');
        $I->sendPost($link, $data_reject_checkin);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals($messages, $response->messages);
    }

    public function deleteDigitalSignatureTest(ApiTester $I, $scenario)
    {
        $I->wantTo('Delete a digital singature');

        // create
        $digital_signature = DigitalSignatures::factory()->create();
        $I->assertInstanceOf(DigitalSignatures::class, $digital_signature);

        // delete
        $I->sendDELETE('/digital_signatures/' . $digital_signature->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals(trans('admin/digital_signatures/message.delete.success'), $response->messages);
    }

    public function digitalSignatureCanCheckout(ApiTester $I)
    {
        $I->wantTo('Test digital signature checkout');

        $digital_signature = $this->digitalSignatureFactory();
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => Location::factory()->create()->id
        ]);
        $data = [
            'name' => $digital_signature->name,
            'checkout_date' => Carbon::now(),
            'assigned_to' => $user->id,
            'note' => 'Testing checkout'
        ];

        $link = '/digital_signatures/' . $digital_signature->id . '/checkout';
        $messages = trans('admin/digital_signatures/message.checkout.success');
        $I->sendPost($link, $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals($messages, $response->messages);
        $I->seeResponseContainsJson([
            'digital_signature' => $digital_signature->seri
        ]);
        $I->sendGet('/digital_signatures/' . $digital_signature->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(config('enum.assigned_status.WAITINGCHECKOUT'),$response->assigned_status);
        $I->assertEquals(config('enum.status_id.ASSIGN'),$response->status_label->id);

        $digital_signature_not_checkoutable = $this->digitalSignatureFactory(
            0,
            config('enum.assigned_status.ACCEPT'),
            config('enum.status_id.ASSIGN'),
            $user->id,
            'App\Models\User',
            $user->id
        );
        $data_not_checkoutable = [
            'name' => $digital_signature_not_checkoutable->name,
            'checkout_date' => Carbon::now(),
            'assigned_to' => $user->id,
            'note' => 'Testing checkout'
        ];
        $link = '/digital_signatures/' . $digital_signature_not_checkoutable->id . '/checkout';
        $messages = trans('admin/digital_signatures/message.checkout.not_available');
        $I->sendPost($link, $data_not_checkoutable);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals($messages, $response->messages);
        $I->seeResponseContainsJson([
            'digital_signature' => $digital_signature_not_checkoutable->seri
        ]);

        $digital_signature_target_not_available = $this->digitalSignatureFactory();
        $data_target_not_available = [
            'name' => $digital_signature_target_not_available->name,
            'checkout_date' => Carbon::now(),
            'assigned_to' => $user->id + 1,
            'note' => 'Testing checkout'
        ];
        $link = '/digital_signatures/' . $digital_signature_target_not_available->id . '/checkout';
        $messages = trans('admin/digital_signatures/message.checkout.error');
        $I->sendPost($link, $data_target_not_available);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals($messages, $response->messages);
    }

    public function digitalSignatureCanCheckin(ApiTester $I)
    {
        $I->wantTo('Test digital signature checkin');

        $user = User::factory()->checkoutAssets()->create([
            'location_id' => Location::factory()->create()->id
        ]);
        $digital_signature = $this->digitalSignatureFactory(
            0,
            config('enum.assigned_status.ACCEPT'),
            config('enum.status_id.ASSIGN'),
            $user->id,
            'App\Models\User',
            $user->id
        );
        $data = [
            'name' => $digital_signature->name,
            'checkin_at' => Carbon::now(),
            'status_id' => config('enum.status_id.READY_TO_DEPLOY'),
            'note' => 'Testing checkin'
        ];
        $link = '/digital_signatures/' . $digital_signature->id . '/checkin';
        $messages = trans('admin/digital_signatures/message.checkin.success');
        $I->sendPost($link, $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals($messages, $response->messages);
        $I->seeResponseContainsJson([
            'digital_signature' => $digital_signature->seri
        ]);
        $I->sendGet('/digital_signatures/' . $digital_signature->id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(config('enum.assigned_status.WAITINGCHECKIN'), $response->assigned_status);
        $I->assertEquals(config('enum.status_id.ASSIGN'), $response->status_label->id);

        $digital_signature_already_checkin = $this->digitalSignatureFactory();
        $data_already_checkin = [
            'name' => $digital_signature_already_checkin->name,
            'checkin_at' => Carbon::now(),
            'status_id' => config('enum.status_id.READY_TO_DEPLOY'),
            'note' => 'Testing checkin'
        ];
        $link = '/digital_signatures/' . $digital_signature_already_checkin->id . '/checkin';
        $messages = trans('admin/digital_signatures/message.checkin.already_checked_in');
        $I->sendPost($link, $data_already_checkin);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals($messages, $response->messages);
        $I->seeResponseContainsJson([
            'signature' => $digital_signature_already_checkin->seri
        ]);

        $digital_signature_not_checkinable = $this->digitalSignatureFactory(
            0,
            config('enum.assigned_status.DEFAULT'),
            config('enum.status_id.READY_TO_DEPLOY'),
            $user->id,
            'App\Models\User',
            $user->id
        );

        $data_not_checkinable = [
            'name' => $digital_signature_not_checkinable->name,
            'checkin_at' => Carbon::now(),
            'status_id' => config('enum.status_id.READY_TO_DEPLOY'),
            'note' => 'Testing checkin'
        ];
        $link = '/digital_signatures/' . $digital_signature_not_checkinable->id . '/checkin';
        $messages = trans('admin/digital_signatures/message.checkin.not_available');
        $I->sendPost($link, $data_not_checkinable);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals($messages, $response->messages);
    }

    public function digitalSignaturesCanMultipleCheckout(ApiTester $I)
    {
        $I->wantTo('Checkout multiple digital signatures');

        $link = 'digital_signatures/checkout';
        $digital_signatures = $this->digitalSignatureFactory(3);
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => Location::factory()->create()->id
        ]);
        $data = [
            'signatures' => $digital_signatures->pluck('id')->toArray(),
            'checkout_date' => Carbon::now(),
            'assigned_to' => $user->id,
            'note' => 'Testing checkout'
        ];
        $messages = trans('admin/digital_signatures/message.checkout.success');
        $I->sendPost($link, $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals($messages, $response->messages);

        $digital_signature_not_checkoutable = $this->digitalSignatureFactory(
            3,
            config('enum.assigned_status.ACCEPT'),
            config('enum.status_id.ASSIGN'),
            $user->id,
            'App\Models\User',
            $user->id
        );
        $data_not_checkoutable = [
            'signatures' => $digital_signature_not_checkoutable->pluck('id')->toArray(),
            'checkout_date' => Carbon::now(),
            'assigned_to' => $user->id,
            'note' => 'Testing checkout'
        ];
        $messages = trans('admin/digital_signatures/message.checkout.not_available');
        $I->sendPost($link, $data_not_checkoutable);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals($messages, $response->messages);

        $digital_signature_target_not_available = $this->digitalSignatureFactory(3);
        $data_target_not_available = [
            'signatures' => $digital_signature_target_not_available->pluck('id')->toArray(),
            'checkout_date' => Carbon::now(),
            'assigned_to' => $user->id + 1,
            'note' => 'Testing checkout'
        ];
        $messages = trans('admin/digital_signatures/message.checkout.error');
        $I->sendPost($link, $data_target_not_available);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals($messages, $response->messages);
    }

    public function digitalSignatureCanMultipleCheckin(ApiTester $I)
    {
        $I->wantTo('Test digital signature multiple checkin');

        $link = '/digital_signatures/checkin';
        $user = User::factory()->checkoutAssets()->create([
            'location_id' => Location::factory()->create()->id
        ]);
        $digital_signatures = $this->digitalSignatureFactory(
            3,
            config('enum.assigned_status.ACCEPT'),
            config('enum.status_id.ASSIGN'),
            $user->id,
            'App\Models\User',
            $user->id
        );
        $data = [
            'signatures' => $digital_signatures->pluck('id')->toArray(),
            'note' => 'Testing checkin'
        ];
        $messages = trans('admin/digital_signatures/message.checkin.success');
        $I->sendPost($link, $data);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('success', $response->status);
        $I->assertEquals($messages, $response->messages);

        $digital_signature_already_checkin = $this->digitalSignatureFactory(3);
        $data_already_checkin = [
            'signatures' => $digital_signature_already_checkin->pluck('id')->toArray(),
            'note' => 'Testing checkin'
        ];
        $messages = trans('admin/digital_signatures/message.checkin.already_checked_in');
        $I->sendPost($link, $data_already_checkin);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals($messages, $response->messages);

        $digital_signature_not_checkinable = $this->digitalSignatureFactory(
            3,
            config('enum.assigned_status.DEFAULT'),
            config('enum.status_id.READY_TO_DEPLOY'),
            $user->id,
            'App\Models\User',
            $user->id
        );
        $data_not_checkinable = [
            'signatures' => $digital_signature_not_checkinable->pluck('id')->toArray(),
            'note' => 'Testing checkin'
        ];
        $messages = trans('admin/digital_signatures/message.checkin.not_available');
        $I->sendPost($link, $data_not_checkinable);
        $response = json_decode($I->grabResponse());
        $I->assertEquals('error', $response->status);
        $I->assertEquals($messages, $response->messages);
    }
}
