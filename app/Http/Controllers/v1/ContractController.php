<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contract;
use App\Models\Client;
use App\Http\Resources\ContractResource;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Services\ContractService;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search'          => 'nullable|string|max:100',
            'status'          => 'nullable|string|in:draft,active,expired,terminated,renewed',
            'date_from'       => 'nullable|date|required_with:date_to',
            'date_to'         => 'nullable|date|required_with:date_from|after_or_equal:date_from',
            'employee_id'     => 'nullable|integer|exists:employees,id',
            'client_id'       => 'nullable|integer|exists:clients,id',
            'contract_number' => 'nullable|string|max:50',
        ]);

        try {
            $user     = auth()->user();
            $query    = Contract::query()->with(['client', 'employee']);

            // Scope non-admins to their own contracts immediately
            if (! $user->hasRole('Admin')) {
                $query->where('employee_id', $user->employee->id);
            }

            // Apply filters directly on the query builder
            $query->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('contract_number', 'like', "%{$search}%")
                        ->orWhereHas('client', fn($c) => $c->where('client_name', 'like', "%{$search}%"));
                });
            });

            $query->when(
                $validated['status'] ?? null,
                fn($q, $status) => $q->where('status', $status)
            );

            $query->when(
                isset($validated['date_from'], $validated['date_to']),
                fn($q) => $q->whereBetween('start_date', [$validated['date_from'], $validated['date_to']])
            );

            $query->when(
                $validated['employee_id'] ?? null,
                fn($q, $id) => $q->where('employee_id', $id)
            );

            $query->when(
                $validated['client_id'] ?? null,
                fn($q, $id) => $q->where('client_id', $id)
            );

            $query->when(
                $validated['contract_number'] ?? null,
                fn($q, $num) => $q->where('contract_number', 'like', "%{$num}%")
            );

            return ContractResource::collection($query->paginate(25));
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to retrieve contracts',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(StoreContractRequest $request, ContractService $contractService)
    {
        $contract = $contractService->create($request->validated());

        return (new ContractResource($contract->load(['client', 'employee', 'services'])))
            ->response()
            ->setStatusCode(201);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Contract $contract)
    {
        try {
             return new ContractResource($contract->load(['client', 'employee', 'services']));
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to retrieve contract',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateContractRequest $request, Contract $contract, ContractService $contractService) {

        $contract = $contractService->update(
            $contract,
            $request->validated()
        );

        return response()->json([
            'message' => 'Contract updated successfully.',
            'data'    => new ContractResource($contract)
        ]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Contract $contract)
    {
        try {
            $contract->delete();

            return response()->json([
                'message' => 'Contract deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to delete contract',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
