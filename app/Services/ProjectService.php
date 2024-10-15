<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ProjectService
{
    protected $secretKey;
    protected $baseApiUrl;

    public function __construct()
    {
        $this->secretKey = env('SECRET_KEY');
        $this->baseApiUrl = env('BASE_API_PROJECT_URL');
    }

    public function getCustomers()
    {
        $customerApiUrl = "{$this->baseApiUrl}/GetAllClients";
        return Http::withHeaders([
            'X-Secret-Key' => $this->secretKey,
            'Accept' => 'application/json',
        ])->withOptions([
            'verify' => false,
        ])->get($customerApiUrl);
    }

    public function getProjects()
    {
        $projectApiUrl = "{$this->baseApiUrl}/GetAllProjects";

        return Http::withHeaders([
            'X-Secret-Key' => $this->secretKey,
            'Accept' => 'application/json',
        ])->withOptions([
            'verify' => false,
        ])->get($projectApiUrl);
    }
}
