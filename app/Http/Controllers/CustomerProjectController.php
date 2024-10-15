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
        $customerResponse = $this->projectService->getCustomers();
        $projectResponse = $this->projectService->getProjects();
            return response()->json([
                'customers' => $customerResponse->json(),
                'projects' => $projectResponse->json(),
            ]);
    }
}
