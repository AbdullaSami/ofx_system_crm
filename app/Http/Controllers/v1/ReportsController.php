<?php

namespace App\Http\Controllers\v1;

use Illuminate\Routing\Controller as BaseController;
use App\Http\Resources\ReportsResource;
use App\Http\Services\ReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends BaseController
{
    public function __construct(protected ReportsService $reportsService)
    {
        $this->middleware('permission:reports.view|reports.view.own')->only('dashboard');
    }

    /**
     * Return the full reports dashboard payload.
     *
     * Optional query parameters:
     *   - from_date            (date)    Start of date range
     *   - to_date              (date)    End of date range
     *   - year                 (integer) Filter by year
     *   - month                (integer) Filter by month (1-12)
     *   - sales_representative (integer) Employee ID
     *   - customer             (integer) Client ID
     *   - service              (string) Service slug
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
            'service'              => 'nullable|string|exists:services,slug',
        ]);

        $user = auth()->user();

        // Scope non-global viewers to their own data only
        if ($user && ! $user->can('reports.view')) {
            if ($user->can('reports.view.own')) {
                $employeeId = $user->employee?->id;
                abort_if(
                    ! $employeeId,
                    403,
                    'Your account has no linked employee record. Contact an administrator.'
                );
                $validated['sales_representative'] = $employeeId;
            } else {
                abort(403, 'You do not have permission to view reports.');
            }
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
