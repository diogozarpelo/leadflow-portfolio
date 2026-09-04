<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Services\LeadIntakeService;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    public function store(
        StoreLeadRequest $request,
        LeadIntakeService $leadIntakeService
    ): JsonResponse {
        $lead = $leadIntakeService->create($request->validated());

        return response()->json([
            'message' => 'Lead received successfully.',
            'lead' => [
                'id' => $lead->id,
                'status' => $lead->status,
            ],
        ], 201);
    }
}
