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
        $branch_temp = strtolower($branch);

        foreach ($locations as $location) {
            if (Str::contains(Str::lower($location->name), $branch_temp)) {
                return $location->id;
            }
        }

        //create new if not exist
        $new_location = new Location;
        $new_location->name  = "NCC " . $branch;
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

        $locations = Location::select(['id', 'name'])->get();

        foreach ($response->result as $value) {
            if (Str::contains($value->email, '@')) {
                $userName = explode("@",  $value->email);

                if ($userName[1] === env('MAIL_DOMAIN')) {

                    $user = User::where('username', $userName[0])->orWhere('username', $userName[0] . ".ncc")->first();
                    $name = $this->handleFullname($value->fullName);

                    if (!$user) {
                        $user = new User;
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
