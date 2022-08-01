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
            $user = User::where('username', $userName[0])->first();
            //if ($user) {
                $user = [
                    'username' => $userName[0],
                    'first_name' => $value->firstName,
                    'last_name' => $value->lastName,
                    'email' => $value->email,
                ];
            //    User::insert($user);
	    //}
	    User::query()->updateOrcreate([
                "username" => $userName[0]
            ], $user);
        }
    }
}
