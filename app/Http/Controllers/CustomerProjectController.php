<?php

namespace App\Http\Controllers;

use App\Services\ProjectService;
use Illuminate\Http\Request;

class CustomerProjectController extends Controller
{
    protected $projectService;
    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    public function index()
    {
        try {
            $customerResponse = $this->projectService->getCustomers();
            $projectResponse = $this->projectService->getProjects();
         
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
