<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use App\Models\Location;

class SyncListUserFromHRMController extends Controller
{
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }
    /**
     * Display a listing of the resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     *
     * @return \Illuminate\Http\Response
     */
    protected function mappingBranch(&$locations, $branch)
    {

        foreach ($locations as $location) {
            if ($location->branch_code === $branch) {
                return $location->id;
            }
        }

        //create new if not exist
        $new_location = new Location;
        $new_location->name  = "NCC " . $branch;
        $new_location->branch_code = $branch;
        $new_location->save();
        $locations[] = $new_location;
        return $new_location->id;
    }

    protected function handleFullname($fullname)
    {
        $fullnameTemp = explode(" ", $fullname);
        $lastName = array_pop($fullnameTemp);
        $firstName = implode(" ", $fullnameTemp);
        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }
    public function syncListUser(Request $request)
    {
        $response = $this->client->get(env('HRM_API'), [
            'headers' => [
                'X-Secret-Key' => env('HRM_SECRET_KEY')
            ]
        ]);
        $response = json_decode($response->getBody());

        if ($response == null || !is_array($response->result))
            return response()->json(Helper::formatStandardApiResponse('error'));

        $locations = Location::select(['id', 'branch_code'])->get();
        foreach ($response->result as $value) {
            if (Str::contains($value->email, '@')) {
                $userName = explode("@",  $value->email);

                if ($userName[1] === env('MAIL_DOMAIN')) {

                    $user = User::where('username', $userName[0])->orWhere('username', $userName[0] . ".ncc")->first();
                    $name = $this->handleFullname($value->fullName);

                    if (!$user) {
                        $user = new User;
                        $user->permissions = '{"superuser":"1","admin":"0","import":"0","reports.view":"0","assets.view":"0","assets.create":"0","assets.edit":"0","assets.delete":"0","assets.checkin":"0","assets.checkout":"0","assets.audit":"0","assets.view.requestable":"0","accessories.view":"0","accessories.create":"0","accessories.edit":"0","accessories.delete":"0","accessories.checkout":"0","accessories.checkin":"0","consumables.view":"0","consumables.create":"0","consumables.edit":"0","consumables.delete":"0","consumables.checkout":"0","licenses.view":"0","licenses.create":"0","licenses.edit":"0","licenses.delete":"0","licenses.checkout":"0","licenses.keys":"0","licenses.files":"0","components.view":"0","components.create":"0","components.edit":"0","components.delete":"0","components.checkout":"0","components.checkin":"0","kits.view":"0","kits.create":"0","kits.edit":"0","kits.delete":"0","kits.checkout":"0","users.view":"0","users.create":"0","users.edit":"0","users.delete":"0","models.view":"0","models.create":"0","models.edit":"0","models.delete":"0","categories.view":"0","categories.create":"0","categories.edit":"0","categories.delete":"0","departments.view":"0","departments.create":"0","departments.edit":"0","departments.delete":"0","statuslabels.view":"0","statuslabels.create":"0","statuslabels.edit":"0","statuslabels.delete":"0","customfields.view":"0","customfields.create":"0","customfields.edit":"0","customfields.delete":"0","suppliers.view":"0","suppliers.create":"0","suppliers.edit":"0","suppliers.delete":"0","manufacturers.view":"0","manufacturers.create":"0","manufacturers.edit":"0","manufacturers.delete":"0","depreciations.view":"0","depreciations.create":"0","depreciations.edit":"0","depreciations.delete":"0","locations.view":"0","locations.create":"0","locations.edit":"0","locations.delete":"0","companies.view":"0","companies.create":"0","companies.edit":"0","companies.delete":"0","self.two_factor":"0","self.api":"0","self.edit_location":"0","self.checkout_assets":"0"}';
                    }
                    $user->username = $userName[0];
                    $user->first_name = $name['first_name'];
                    $user->last_name = $name['last_name'];
                    $user->email = $value->email;
                    $user->job_position_code = $value->jobPositionCode;
                    $user->user_type = $value->userTypeName;
                    $user->location_id = $this->mappingBranch($locations, $value->branchCode);
                    $user->save();
                }
            }
        }
    }
}
