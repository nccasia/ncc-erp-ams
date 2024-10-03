<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;
class CustomerProjectController extends Controller
{
    public function index()
    {
        $customerApiUrl = env('CUSTOMER_API_URL');
        $projectApiUrl = env('PROJECT_API_URL');
        $secretKey = env('SECRET_KEY');
        try {
            
           $customerResponse = Http::withHeaders([
            'X-Secret-Key' => $secretKey,
            'Accept' => 'application/json',
                ])->withOptions([
                    'verify' => false,
                ])->get($customerApiUrl);


            $projectResponse = Http::withHeaders([
                'X-Secret-Key' => $secretKey,
                'Accept' => 'application/json',
                ])->withOptions([
                    'verify' => false,
                ])->get($projectApiUrl);

            if ($customerResponse->successful() && $projectResponse->successful()) {
                return response()->json([
                    'customers' => $customerResponse->json(),
                    'projects' => $projectResponse->json(),
                ]);
            }

            return response()->json(['error' => 'Failed to fetch data from API'], 500);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error fetching data from API',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}