<?php

namespace App\Http\Controllers;

use App\Domains\Finfast\Services\FinfastService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;


/**
 * This controller handles all actions related to the Admin Dashboard
 * for the Snipe-IT Asset Management application.
 *
 * @author A. Gianotto <snipe@snipe.net>
 * @version v1.0
 */
class DashboardController extends Controller
{
    /**
     * @var FinfastService
     */
    private FinfastService $finfastService;

    public function __construct(FinfastService $finfastService)
    {
        $this->finfastService = $finfastService;
    }

    /**
     * Check authorization and display admin dashboard, otherwise display
     * the user's checked-out assets.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v1.0]
     * @return View
     */
    public function index()
    {
        // Show the page
        if (Auth::user()->hasAccess('admin')) {
            $asset_stats = null;

            $counts['asset'] = \App\Models\Asset::count();
            $counts['accessory'] = \App\Models\Accessory::count();
            $counts['license'] = \App\Models\License::assetcount();
            $counts['consumable'] = \App\Models\Consumable::count();
            $counts['component'] = \App\Models\Component::count();
            $counts['user'] = \App\Models\User::count();
            $counts['grand_total'] = $counts['asset'] + $counts['accessory'] + $counts['license'] + $counts['consumable'];

            if ((! file_exists(storage_path().'/oauth-private.key')) || (! file_exists(storage_path().'/oauth-public.key'))) {
                Artisan::call('migrate', ['--force' => true]);
                \Artisan::call('passport:install');
            }

            return view('dashboard')->with('asset_stats', $asset_stats)->with('counts', $counts);
        } else {
            // Redirect to the profile page
            return redirect()->intended('account/view-assets');
        }
    }

    public function getFinfast(Request $request) {
        $from = $request->from;
        $to = $request->to;
        return $this->finfastService->getListOutcome($from, $to);
    }
    public function getListEntryType() {
        return $this->finfastService->getListEntryType();
    }
    public function saveEntryIdFilter(Request $request) {
        return $this->finfastService->saveEntryIdFilter(json_decode($request->value));
    }
    public function getEntryIdFilter() {
        return $this->finfastService->getEntryIdFilter();
    }
}
