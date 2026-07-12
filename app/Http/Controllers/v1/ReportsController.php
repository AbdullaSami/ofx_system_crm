<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportsResource;
use App\Http\Services\ReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __construct(protected ReportsService $reportsService)
    {
    }

    /**
     * Return the full reports dashboard payload.
     *
     * Optional query parameters:
     *   - from_date        (date)    Start of date range
     *   - to_date          (date)    End of date range
     *   - year             (integer) Filter by year
     *   - month            (integer) Filter by month (1-12)
     *   - sales_representative (integer) Employee ID
     *   - customer         (integer) Client ID
     *   - service          (integer) Service ID
     */
    public function dashboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date'            => 'nullable|date',
            'to_date'              => 'nullable|date|after_or_equal:from_date',
            'year'                 => 'nullable|integer|min:2000|max:2100',
            'month'                => 'nullable|integer|min:1|max:12',
            'sales_representative' => 'nullable|integer|exists:employees,id',
            'customer'             => 'nullable|integer|exists:clients,id',
            'service'              => 'nullable|integer|exists:services,id',
        ]);

        // Non-admin users are scoped to their own data only
        $user = auth()->user();
        if ($user && !$user->hasRole('Admin')) {
            $validated['sales_representative'] = $user->employee->id ?? null;
        }

        try {
            $data = $this->reportsService->getDashboardData($validated);

            return response()->json(new ReportsResource($data));
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to retrieve reports',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
