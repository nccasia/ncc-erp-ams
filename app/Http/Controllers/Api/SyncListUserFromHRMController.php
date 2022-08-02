<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserRequest;
use App\Http\Transformers\AccessoriesTransformer;
use App\Http\Transformers\AssetsTransformer;
use App\Http\Transformers\ConsumablesTransformer;
use App\Http\Transformers\LicensesTransformer;
use App\Http\Transformers\SelectlistTransformer;
use Illuminate\Support\Facades\Http;
use App\Http\Transformers\UsersTransformer;
use App\Models\Asset;
use App\Models\Company;
use App\Models\License;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use App\Http\Requests\ImageUploadRequest;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Http\Controllers\UserController;

class SyncListUserFromHRMController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     *
     * @return \Illuminate\Http\Response
     */
    public function syncListUser(Request $request)
    {
        $response = Http::get(env('HRM_API'));
        $response = json_decode($response);
        if ($response == null || !is_array($response->result))
            return response()->json(Helper::formatStandardApiResponse('error'));
        foreach ($response->result as $value) {
            if (!$response) continue;
            $userName = explode("@",  $value->email);
            $user = User::where('username', $userName[0])->orWhere('username', $userName[0].".ncc")->first();
            if (!$user) {
                $user_data = [
                    'username' => $userName[0],
                    'first_name' => $value->firstName,
                    'last_name' => $value->lastName,
		    'email' => $value->email,
                    'permissions' => ''{"superuser":"1","admin":"0","import":"0","reports.view":"0","assets.view":"0","assets.create":"0","assets.edit":"0","assets.delete":"0","assets.checkin":"0","assets.checkout":"0","assets.audit":"0","assets.view.requestable":"0","accessories.view":"0","accessories.create":"0","accessories.edit":"0","accessories.delete":"0","accessories.checkout":"0","accessories.checkin":"0","consumables.view":"0","consumables.create":"0","consumables.edit":"0","consumables.delete":"0","consumables.checkout":"0","licenses.view":"0","licenses.create":"0","licenses.edit":"0","licenses.delete":"0","licenses.checkout":"0","licenses.keys":"0","licenses.files":"0","components.view":"0","components.create":"0","components.edit":"0","components.delete":"0","components.checkout":"0","components.checkin":"0","kits.view":"0","kits.create":"0","kits.edit":"0","kits.delete":"0","kits.checkout":"0","users.view":"0","users.create":"0","users.edit":"0","users.delete":"0","models.view":"0","models.create":"0","models.edit":"0","models.delete":"0","categories.view":"0","categories.create":"0","categories.edit":"0","categories.delete":"0","departments.view":"0","departments.create":"0","departments.edit":"0","departments.delete":"0","statuslabels.view":"0","statuslabels.create":"0","statuslabels.edit":"0","statuslabels.delete":"0","customfields.view":"0","customfields.create":"0","customfields.edit":"0","customfields.delete":"0","suppliers.view":"0","suppliers.create":"0","suppliers.edit":"0","suppliers.delete":"0","manufacturers.view":"0","manufacturers.create":"0","manufacturers.edit":"0","manufacturers.delete":"0","depreciations.view":"0","depreciations.create":"0","depreciations.edit":"0","depreciations.delete":"0","locations.view":"0","locations.create":"0","locations.edit":"0","locations.delete":"0","companies.view":"0","companies.create":"0","companies.edit":"0","companies.delete":"0","self.two_factor":"0","self.api":"0","self.edit_location":"0","self.checkout_assets":"0"}// todo
                ];
                User::insert($user_data);
	    } else {
	       $user->username = $userName[0];
               $user->first_name = $value->firstName;
               $user->last_name = $value->lastName;
	       $user->email = $value->email;	     
	       $user->save();
	    }
        }
    }
}
