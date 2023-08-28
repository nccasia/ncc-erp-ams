<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserRequest;
use App\Http\Transformers\AccessoriesTransformer;
use App\Http\Transformers\AssetsTransformer;
use App\Http\Transformers\ConsumablesTransformer;
use App\Http\Transformers\DatatablesTransformer;
use App\Http\Transformers\LicensesTransformer;
use App\Http\Transformers\SelectlistTransformer;
use App\Http\Transformers\UsersTransformer;
use App\Models\Asset;
use App\Models\Company;
use App\Models\License;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use App\Http\Requests\ImageUploadRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize('view', User::class);

        $users = User::select([
            'users.activated',
            'users.address',
            'users.avatar',
            'users.city',
            'users.company_id',
            'users.country',
            'users.created_at',
            'users.deleted_at',
            'users.department_id',
            'users.email',
            'users.employee_num',
            'users.first_name',
            'users.id',
            'users.jobtitle',
            'users.last_login',
            'users.last_name',
            'users.locale',
            'users.location_id',
            'users.manager_id',
            'users.notes',
            'users.permissions',
            'users.phone',
            'users.state',
            'users.two_factor_enrolled',
            'users.two_factor_optin',
            'users.updated_at',
            'users.username',
            'users.manager_location',
            'users.zip',
            'users.remote',
            'users.ldap_import',
            'users.user_type',
            'users.job_position_code'

        ])->with('manager', 'groups', 'userloc', 'company', 'department', 'assets', 'licenses', 'accessories', 'consumables')
            ->withCount('assets as assets_count', 'licenses as licenses_count', 'accessories as accessories_count', 'consumables as consumables_count');
        $users = Company::scopeCompanyables($users);


        if (($request->filled('deleted')) && ($request->input('deleted') == 'true')) {
            $users = $users->onlyTrashed();
        } elseif (($request->filled('all')) && ($request->input('all') == 'true')) {
            $users = $users->withTrashed();
        }

        if ($request->filled('activated')) {
            $users = $users->where('users.activated', '=', $request->input('activated'));
        }

        if ($request->filled('company_id')) {
            $users = $users->where('users.company_id', '=', $request->input('company_id'));
        }

        if ($request->filled('location')) {
            $users = $users->whereIn('users.location_id', $request->input('location'));
        }

        if ($request->filled('email')) {
            $users = $users->where('users.email', '=', $request->input('email'));
        }

        if ($request->filled('username')) {
            $users = $users->where('users.username', '=', $request->input('username'));
        }

        if ($request->filled('first_name')) {
            $users = $users->where('users.first_name', '=', $request->input('first_name'));
        }

        if ($request->filled('last_name')) {
            $users = $users->where('users.last_name', '=', $request->input('last_name'));
        }

        if ($request->filled('employee_num')) {
            $users = $users->where('users.employee_num', '=', $request->input('employee_num'));
        }

        if ($request->filled('state')) {
            $users = $users->where('users.state', '=', $request->input('state'));
        }

        if ($request->filled('country')) {
            $users = $users->where('users.country', '=', $request->input('country'));
        }

        if ($request->filled('zip')) {
            $users = $users->where('users.zip', '=', $request->input('zip'));
        }

        if ($request->filled('group_id')) {
            $users = $users->ByGroup($request->get('group_id'));
        }

        if ($request->filled('department_id')) {
            $users = $users->where('users.department_id', '=', $request->input('department_id'));
        }

        if ($request->filled('manager_id')) {
            $users = $users->where('users.manager_id','=',$request->input('manager_id'));
        }

        if ($request->filled('ldap_import')) {
            $users = $users->where('ldap_import', '=', $request->input('ldap_import'));
        }

        if ($request->filled('remote')) {
            $users = $users->where('remote', '=', $request->input('remote'));
        }

        if($request->filled('user_type')) {
            $users = $users->whereIn('user_type', $request->input('user_type'));
        }

        if($request->filled('job_position_code')) {
            $users = $users->whereIn('job_position_code', $request->input('job_position_code'));
        }

        if ($request->filled('assets_count')) {
            $users->has('assets', '=', $request->input('assets_count'));
        }

        if ($request->filled('consumables_count')) {
            $users->has('consumables', '=', $request->input('consumables_count'));
        }

        if ($request->filled('licenses_count')) {
            $users->has('licenses', '=', $request->input('licenses_count'));
        }

        if ($request->filled('accessories_count')) {
            $users->has('accessories', '=', $request->input('accessories_count'));
        }

        if ($request->filled('search')) {
            $users = $users->TextSearch($request->input('search'));
        }

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $offset = (($users) && (request('offset') > $users->count())) ? 0 : request('offset', 0);

        // Set the offset to the API call's offset, unless the offset is higher than the actual count of items in which
        // case we override with the actual count, so we should return 0 items.
        $offset = (($users) && ($request->get('offset') > $users->count())) ? $users->count() : $request->get('offset', 0);

        // Check to make sure the limit is not higher than the max allowed
        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit'))) ? $limit = $request->input('limit') : $limit = config('app.max_results');


        switch ($request->input('sort')) {
            case 'manager':
                $users = $users->OrderManager($order);
                break;
            case 'location':
                $users = $users->OrderLocation($order);
                break;
            case 'department':
                $users = $users->OrderDepartment($order);
                break;
            case 'company':
                $users = $users->OrderCompany($order);
                break;
            default:
                $allowed_columns =
                    [
                        'last_name', 'first_name', 'email', 'jobtitle', 'username', 'employee_num',
                        'assets', 'accessories', 'consumables', 'licenses', 'groups', 'activated', 'created_at',
                        'two_factor_enrolled', 'two_factor_optin', 'last_login', 'assets_count', 'licenses_count',
                        'consumables_count', 'accessories_count', 'phone', 'address', 'city', 'state',
                        'country', 'zip', 'id', 'ldap_import', 'remote',
                    ];

                $sort = in_array($request->get('sort'), $allowed_columns) ? $request->get('sort') : 'first_name';
                $users = $users->orderBy($sort, $order);
                break;
        }

        $total = $users->count();
        $users = $users->skip($offset)->take($limit)->get();

        return (new UsersTransformer)->transformUsers($users, $total);
    }

    /**
     * Gets a paginated collection for the select2 menus
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0.16]
     * @see \App\Http\Transformers\SelectlistTransformer
     */
    public function selectlist(Request $request)
    {
        $users = User::select(
            [
                'users.id',
                'users.username',
                'users.employee_num',
                'users.first_name',
                'users.last_name',
                'users.gravatar',
                'users.avatar',
                'users.email',
            ]
        )->where('show_in_list', '=', '1');

        $users = Company::scopeCompanyables($users);

        if ($request->filled('location_id')) {
            $users = $users->where('location_id', '=', $request->get('location_id'));
        }

        if ($request->filled('search')) {
            $users = $users->SimpleNameSearch($request->get('search'));
        }
        
        $users = $users->orderBy('last_name', 'asc')->orderBy('first_name', 'asc');
        $users = $users->paginate(800);

        foreach ($users as $user) {
            $name_str = '';
            if ($user->last_name != '') {
                $name_str .= $user->last_name.', ';
            }
            $name_str .= $user->first_name;

            if ($user->username != '') {
                $name_str .= ' ('.$user->username.')';
            }

            if ($user->employee_num != '') {
                $name_str .= ' - #'.$user->employee_num;
            }

            $user->use_text = $name_str;
            $user->use_image = ($user->present()->gravatar) ? $user->present()->gravatar : null;
        }

        return (new SelectlistTransformer)->transformSelectlist($users);
    }



    /**
     * Store a newly created resource in storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SaveUserRequest $request)
    {
        $this->authorize('create', User::class);

        $user = new User;
        $user->fill($request->all());

        if ($request->has('permissions')) {
            $permissions_array = $request->input('permissions');

            // Strip out the superuser permission if the API user isn't a superadmin
            if (! Auth::user()->isSuperUser()) {
                unset($permissions_array['superuser']);
            }

            // Return error if user is branchadmin but haven't manager_location
            $permissions = json_decode($permissions_array, true);
            $manager_location = json_decode($request->input('manager_location'), true);
            if (
                isset($permissions['branchadmin']) &&
                $permissions['branchadmin'] == config('enum.permission_status.ALLOW') &&
                empty($manager_location)
            ) {
                $error['manager_location'] = trans('admin/users/message.manager_location');
                return response()->json(Helper::formatStandardApiResponse('error', null, $error));
            }

            $user->permissions = $permissions_array;
        }

        $tmp_pass = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 20);
        $user->password = bcrypt($request->get('password', $tmp_pass));

        app('App\Http\Requests\ImageUploadRequest')->handleImages($user, 600, 'image', 'avatars', 'avatar');

        if ($user->save()) {
            if ($request->filled('groups')) {
                $user->groups()->sync($request->input('groups'));
            } else {
                $user->groups()->sync([]);
            }

            return response()->json(Helper::formatStandardApiResponse('success', (new UsersTransformer)->transformUser($user), trans('admin/users/message.success.create')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $user->getErrors()), Response::HTTP_BAD_REQUEST);
    }

    /**
     * Display the specified resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize('view', User::class);
        $user = User::withCount('assets as assets_count', 'licenses as licenses_count', 'accessories as accessories_count', 'consumables as consumables_count')->findOrFail($id);

        return (new UsersTransformer)->transformUser($user);
    }


    /**
     * Update the specified resource in storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(SaveUserRequest $request, $id)
    {
        $this->authorize('update', User::class);

        $user = User::findOrFail($id);

        /**
         * This is a janky hack to prevent people from changing admin demo user data on the public demo.
         * 
         * The $ids 1 and 2 are special since they are seeded as superadmins in the demo seeder.
         * 
         *  Thanks, jerks. You are why we can't have nice things. - snipe
         * 
         */


        if ((($id == 1) || ($id == 2)) && (config('app.lock_passwords'))) {
            return response()->json(Helper::formatStandardApiResponse('error', null, 'Permission denied. You cannot update user information via API on the demo.'));
        }


        $user->fill($request->all());

        if ($user->id == $request->input('manager_id')) {
            return response()->json(Helper::formatStandardApiResponse('error', null, 'You cannot be your own manager'));
        }

        if ($request->filled('password')) {
            $user->password = bcrypt($request->input('password'));
        }

        // We need to use has()  instead of filled()
        // here because we need to overwrite permissions
        // if someone needs to null them out
        if ($request->has('permissions')) {
            $permissions_array = $request->input('permissions');

            // Strip out the superuser permission if the API user isn't a superadmin
            if (! Auth::user()->isSuperUser()) {
                unset($permissions_array['superuser']);
            }
            
            $permissions = json_decode($permissions_array, true);
            if (isset($permissions['branchadmin'])) {

                //Delete list manager_location if User isn't a branchadmin
                if ($permissions['branchadmin'] != config('enum.permission_status.ALLOW')) {
                    $user->manager_location = null;
                }
                
                //Return error if user is branchadmin but haven't manager_location
                $manager_location = json_decode($request->input('manager_location'), true);
                if ($permissions['branchadmin'] == config('enum.permission_status.ALLOW') && empty($manager_location)) {
                    $error['manager_location'] = trans('admin/users/message.manager_location');
                    return response()->json(Helper::formatStandardApiResponse('error', null, $error));
                }
            }

            $user->permissions = $permissions_array;
        }



        // Update the location of any assets checked out to this user
        Asset::where('assigned_type', User::class)
            ->where('assigned_to', $user->id)->update(['location_id' => $request->input('location_id', null)]);


        app('App\Http\Requests\ImageUploadRequest')->handleImages($user, 600, 'image', 'avatars', 'avatar');

        if ($user->save()) {

            // Sync group memberships:
            // This was changed in Snipe-IT v4.6.x to 4.7, since we upgraded to Laravel 5.5
            // which changes the behavior of has vs filled.
            // The $request->has method will now return true even if the input value is an empty string or null.
            // A new $request->filled method has was added that provides the previous behavior of the has method.

            // Check if the request has groups passed and has a value
            if ($request->filled('groups')) {
                $user->groups()->sync($request->input('groups'));
                // The groups field has been passed but it is null, so we should blank it out
            } elseif ($request->has('groups')) {
                $user->groups()->sync([]);
            }


            return response()->json(Helper::formatStandardApiResponse('success', (new UsersTransformer)->transformUser($user), trans('admin/users/message.success.update')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $user->getErrors()));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authorize('delete', User::class);
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);

        if (($user->assets) && ($user->assets->count() > 0)) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/users/message.error.delete_has_assets')));
        }

        if (($user->licenses) && ($user->licenses->count() > 0)) {
            return response()->json(Helper::formatStandardApiResponse('error', null, 'This user still has '.$user->licenses->count().' license(s) associated with them and cannot be deleted.'));
        }

        if (($user->accessories) && ($user->accessories->count() > 0)) {
            return response()->json(Helper::formatStandardApiResponse('error', null, 'This user still has '.$user->accessories->count().' accessories associated with them.'));
        }

        if (($user->managedLocations()) && ($user->managedLocations()->count() > 0)) {
            return response()->json(Helper::formatStandardApiResponse('error', null, 'This user still has '.$user->managedLocations()->count().' locations that they manage.'));
        }

        if ($user->delete()) {

            // Remove the user's avatar if they have one
            if (Storage::disk('public')->exists('avatars/'.$user->avatar)) {
                try {
                    Storage::disk('public')->delete('avatars/'.$user->avatar);
                } catch (\Exception $e) {
                    \Log::debug($e);
                }
            }

            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/users/message.success.delete')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/users/message.error.delete')));
    }

    /**
     * Return JSON containing a list of assets assigned to a user.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @param $userId
     * @return string JSON
     */
    public function assets(Request $request, $id)
    {
        $this->authorize('view', User::class);
        $this->authorize('view', Asset::class);
        $assets = Asset::where('assigned_to', '=', $id)->where('assigned_type', '=', User::class)->with('model')->get();

        return (new AssetsTransformer)->transformAssets($assets, $assets->count(), $request);
    }


    /**
     * Return JSON containing a list of consumables assigned to a user.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @param $userId
     * @return string JSON
     */
    public function consumables(Request $request, $id)
    {
        $this->authorize('view', User::class);
        $this->authorize('view', Consumable::class);
        $user = User::findOrFail($id);
        $consumables = $user->consumables;
        return (new ConsumablesTransformer)->transformConsumables($consumables, $consumables->count(), $request);
    }

    /**
     * Return JSON containing a list of accessories assigned to a user.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.6.14]
     * @param $userId
     * @return string JSON
     */
    public function accessories($id)
    {
        $this->authorize('view', User::class);
        $user = User::findOrFail($id);
        $this->authorize('view', Accessory::class);
        $accessories = $user->accessories;

        return (new AccessoriesTransformer)->transformAccessories($accessories, $accessories->count());
    }

    /**
     * Return JSON containing a list of licenses assigned to a user.
     *
     * @author [N. Mathar] [<snipe@snipe.net>]
     * @since [v5.0]
     * @param $userId
     * @return string JSON
     */
    public function licenses($id)
    {
        $this->authorize('view', User::class);
        $this->authorize('view', License::class);
        $user = User::where('id', $id)->withTrashed()->first();
        $licenses = $user->licenses()->get();

        return (new LicensesTransformer())->transformLicenses($licenses, $licenses->count());
    }

    /**
     * Reset the user's two-factor status
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @param $userId
     * @return string JSON
     */
    public function postTwoFactorReset(Request $request)
    {
        $this->authorize('update', User::class);

        if ($request->filled('id')) {
            try {
                $user = User::find($request->get('id'));
                $user->two_factor_secret = null;
                $user->two_factor_enrolled = 0;
                $user->save();

                return response()->json(['message' => trans('admin/settings/general.two_factor_reset_success')], 200);
            } catch (\Exception $e) {
                return response()->json(['message' => trans('admin/settings/general.two_factor_reset_error')], 500);
            }
        }
        return response()->json(['message' => 'No ID provided'], 500);


    }

    /**
     * Get info on the current user.
     *
     * @author [Juan Font] [<juanfontalonso@gmail.com>]
     * @since [v4.4.2]
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getCurrentUserInfo(Request $request)
    {
        $user = (new UsersTransformer)->transformUser($request->user());
        if (Auth::user()->isAdmin()) {
            $user['role'] = "admin";
        } elseif (Auth::user()->isSuperUser()) {
            $user['role'] = "user";
        }
        return $user;
    }

    /**
     * Restore a soft-deleted user.
     *
     * @author [E. Taylor] [<dev@evantaylor.name>]
     * @param int $userId
     * @since [v6.0.0]
     * @return JsonResponse
     */
    public function restore($userId = null)
    {
        // Get asset information
        $user = User::withTrashed()->find($userId);
        $this->authorize('delete', $user);
        if (isset($user->id)) {
            // Restore the user
            User::withTrashed()->where('id', $userId)->restore();

            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/users/message.success.restored')));
        }

        $id = $userId;
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/users/message.user_not_found', compact('id'))), 200);
    }

    public function loginGoogle(){
        if(Helper::checkValidEmail(request()->profile_obj['email'])){
            $user = [
                "first_name" => request()->profile_obj['familyName'],
                "last_name" => request()->profile_obj['givenName'],
                "email" => request()->profile_obj['email'],
                "username" => request()->profile_obj['email'],
                "social_id" => request()->profile_obj['googleId'],
                "access_token_social" => request()->client_secret['access_token'],
                "platform" => "google",
                "password" => Helper::generateEncyrptedPassword(),
                "permissions" => '{"superuser":"1","admin":"0","import":"0","reports.view":"0","assets.view":"0","assets.create":"0","assets.edit":"0","assets.delete":"0","assets.checkin":"0","assets.checkout":"0","assets.audit":"0","assets.view.requestable":"0","accessories.view":"0","accessories.create":"0","accessories.edit":"0","accessories.delete":"0","accessories.checkout":"0","accessories.checkin":"0","consumables.view":"0","consumables.create":"0","consumables.edit":"0","consumables.delete":"0","consumables.checkout":"0","licenses.view":"0","licenses.create":"0","licenses.edit":"0","licenses.delete":"0","licenses.checkout":"0","licenses.keys":"0","licenses.files":"0","components.view":"0","components.create":"0","components.edit":"0","components.delete":"0","components.checkout":"0","components.checkin":"0","kits.view":"0","kits.create":"0","kits.edit":"0","kits.delete":"0","kits.checkout":"0","users.view":"0","users.create":"0","users.edit":"0","users.delete":"0","models.view":"0","models.create":"0","models.edit":"0","models.delete":"0","categories.view":"0","categories.create":"0","categories.edit":"0","categories.delete":"0","departments.view":"0","departments.create":"0","departments.edit":"0","departments.delete":"0","statuslabels.view":"0","statuslabels.create":"0","statuslabels.edit":"0","statuslabels.delete":"0","customfields.view":"0","customfields.create":"0","customfields.edit":"0","customfields.delete":"0","suppliers.view":"0","suppliers.create":"0","suppliers.edit":"0","suppliers.delete":"0","manufacturers.view":"0","manufacturers.create":"0","manufacturers.edit":"0","manufacturers.delete":"0","depreciations.view":"0","depreciations.create":"0","depreciations.edit":"0","depreciations.delete":"0","locations.view":"0","locations.create":"0","locations.edit":"0","locations.delete":"0","companies.view":"0","companies.create":"0","companies.edit":"0","companies.delete":"0","self.two_factor":"0","self.api":"0","self.edit_location":"0","self.checkout_assets":"0"}'
            ];
            $userCreate = User::query()->updateOrcreate([
                "email" => $user['email']
            ], $user);

            $token = $userCreate->createToken('google-login')->accessToken;

            return response()->json([
                "token_type" => "Bear",
                "access_token" => $token,
            ]);
        } else {
            return response()->json([
                "message" => "Unauthorized",
            ], 401);
        }
    }

    public function loginGoogleV2(){
        $username = explode('@', request()->profile_obj['email'])[0];//todo check email
        $found = User::where('username', $username)->first();
        if(!$found) {
            return response()->json([
                "message" => "User not found",
            ], 400);
        }
        if(Helper::checkValidEmail(request()->profile_obj['email'])){
            $user = [
                "email" => request()->profile_obj['email'],
                "username" => $username,
                "social_id" => request()->profile_obj['googleId'],
                "access_token_social" => request()->client_secret['access_token'],
                // "platform" => "google",
                // "permissions" => '{"superuser":"1","admin":"0","import":"0","reports.view":"0","assets.view":"0","assets.create":"0","assets.edit":"0","assets.delete":"0","assets.checkin":"0","assets.checkout":"0","assets.audit":"0","assets.view.requestable":"0","accessories.view":"0","accessories.create":"0","accessories.edit":"0","accessories.delete":"0","accessories.checkout":"0","accessories.checkin":"0","consumables.view":"0","consumables.create":"0","consumables.edit":"0","consumables.delete":"0","consumables.checkout":"0","licenses.view":"0","licenses.create":"0","licenses.edit":"0","licenses.delete":"0","licenses.checkout":"0","licenses.keys":"0","licenses.files":"0","components.view":"0","components.create":"0","components.edit":"0","components.delete":"0","components.checkout":"0","components.checkin":"0","kits.view":"0","kits.create":"0","kits.edit":"0","kits.delete":"0","kits.checkout":"0","users.view":"0","users.create":"0","users.edit":"0","users.delete":"0","models.view":"0","models.create":"0","models.edit":"0","models.delete":"0","categories.view":"0","categories.create":"0","categories.edit":"0","categories.delete":"0","departments.view":"0","departments.create":"0","departments.edit":"0","departments.delete":"0","statuslabels.view":"0","statuslabels.create":"0","statuslabels.edit":"0","statuslabels.delete":"0","customfields.view":"0","customfields.create":"0","customfields.edit":"0","customfields.delete":"0","suppliers.view":"0","suppliers.create":"0","suppliers.edit":"0","suppliers.delete":"0","manufacturers.view":"0","manufacturers.create":"0","manufacturers.edit":"0","manufacturers.delete":"0","depreciations.view":"0","depreciations.create":"0","depreciations.edit":"0","depreciations.delete":"0","locations.view":"0","locations.create":"0","locations.edit":"0","locations.delete":"0","companies.view":"0","companies.create":"0","companies.edit":"0","companies.delete":"0","self.two_factor":"0","self.api":"0","self.edit_location":"0","self.checkout_assets":"0"}'// todo 
            ];
            $userCreate = User::query()->updateOrcreate([
                "username" => $username
            ], $user);

            $permissions = json_decode($userCreate->permissions, TRUE);
            $scopes = [];
            foreach($permissions as $key => $value){
                if($value == "1"){
                    $scopes[] = $key;
                }
            }

            $token = $userCreate->createToken('google-login', $scopes)->accessToken;
            return response()->json([
                "token_type" => "Bear",
                "access_token" => $token,
            ]);
        }
        else {
            return response()->json([
                "message" => "Unauthorized",
            ], 401);
        }
    }


    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required|min:6',
        ]);

        if (!Auth::attempt($request->only('username', 'password'))) {
            return response()->json([
                'message' => 'Thông tin đăng nhập không chính xác',
            ], 401);
        }

        $user = User::where('username', $request['username'])->firstOrFail();

        $permissions = json_decode($user->permissions, TRUE);
        $scopes = [];
        foreach($permissions as $key => $value){
            if($value == "1"){
                $scopes[] = $key;
            }
        }

        $token = $user->createToken('google-login', $scopes)->accessToken;
        return response()->json([
            "token_type" => "Bear",
            "access_token" => $token,
        ]);
    }

    public function getListUserType()
    {
        $list = User::select('users.user_type as name')->distinct()->get();
        return (new DatatablesTransformer)->transformDatatables($list);
    }

    public function getListJobPosition()
    {
        $list = User::select('users.job_position_code as name')->distinct()->get();
        return (new DatatablesTransformer)->transformDatatables($list);
    }

}
