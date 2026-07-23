<?php

namespace App\Http\Controllers\v1;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\Models\Contract;
use App\Http\Resources\ContractResource;
use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Http\Services\ContractService;
use App\Http\Services\CommissionService;
use App\Http\Concerns\AuthorizesScope;

class ContractController extends BaseController
{
    use AuthorizesScope;

    public function __construct()
    {
        // All contract routes require authentication — no exceptions
        $this->middleware('permission:contracts.view|contracts.view.own')->only('index');
        $this->middleware('permission:contracts.view|contracts.view.own')->only('show');
        $this->middleware('permission:contracts.create')->only('store');
        $this->middleware('permission:contracts.update|contracts.update.own')->only('update');
        $this->middleware('permission:contracts.delete|contracts.delete.own')->only('destroy');
        $this->middleware('permission:contracts.cancel')->only(['cancelContract', 'cancelSingleService']);
    }

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
            $user  = auth()->user();
            $query = Contract::query()
                ->with(['client', 'employee'])
                ->visibleTo($user);  // Data-scope: respects contracts.view vs contracts.view.own

            $query->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('contract_number', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($c) use ($search) {
                            $c->where('client_name', 'like', "%{$search}%")
                                ->orWhere('company', 'like', "%{$search}%");
                        });
                });
            });

            $query->when(
                $validated['status'] ?? null,
                fn ($q, $status) => $q->where('status', $status)
            );

            $query->when(
                isset($validated['date_from'], $validated['date_to']),
                fn ($q) => $q->whereBetween('start_date', [$validated['date_from'], $validated['date_to']])
            );

            $query->when(
                $validated['employee_id'] ?? null,
                fn ($q, $id) => $q->where('employee_id', $id)
            );

            $query->when(
                $validated['client_id'] ?? null,
                fn ($q, $id) => $q->where('client_id', $id)
            );

            $query->when(
                $validated['contract_number'] ?? null,
                fn ($q, $num) => $q->where('contract_number', 'like', "%{$num}%")
            );

            $contracts = $query->orderBy('created_at', 'desc')->paginate(25);

            $contracts->getCollection()->load(
                $this->contractRelationsForResource()
            );

            $contracts->getCollection()->transform(function ($contract) {
                $contract->services->each(function ($service) use ($contract) {
                    $service->setRelation(
                        'collections',
                        $service->collections
                            ->where('contract_id', $contract->id)
                            ->values()
                    );
                });

                return $contract;
            });

            return ContractResource::collection($contracts);
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
        $contract    = $contractService->create($request->validated());
        $commission  = new CommissionService;
        $commission->addCommission($contract->id, $contract->amount, $contract->employee_id);

        return (new ContractResource($contract->load($this->contractRelationsForResource())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Contract $contract)
    {
        // Ownership check for own-scoped users
        $this->authorizeRecordAccess($contract, 'contracts', 'view', 'employee_id');

        try {
            return new ContractResource($contract->load($this->contractRelationsForResource()));
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to retrieve contract',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContractRequest $request, Contract $contract, ContractService $contractService)
    {
        // Ownership check for own-scoped users
        $this->authorizeRecordAccess($contract, 'contracts', 'update', 'employee_id');

        $contract = $contractService->update(
            $contract,
            $request->validated()
        );

        $commission = new CommissionService;
        $commission->updateCommission($contract->id, $contract->amount, $contract->employee_id);

        return response()->json([
            'message' => 'Contract updated successfully.',
            'data'    => new ContractResource($contract->load($this->contractRelationsForResource()))
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contract $contract)
    {
        // Ownership check for own-scoped users
        $this->authorizeRecordAccess($contract, 'contracts', 'delete', 'employee_id');

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

    /**
     * Cancel a full contract — requires contracts.cancel permission.
     * Manages refund logic for collections associated with the contract services.
     */
    public function cancelContract(Contract $contract)
    {
        // Ownership check: own-scoped users may only cancel their own contracts
        $this->authorizeRecordAccess($contract, 'contracts', 'update', 'employee_id');

        try {
            $contract->update([
                'status'          => 'terminated',
                'is_terminated'   => true,
                'terminated_date' => now(),
            ]);
            $contract->services()->update([
                'status'       => 'cancelled',
                'is_cancelled' => true,
                'cancelled_date' => now(),
            ]);

            foreach ($contract->services as $service) {
                foreach ($service->collectionsForContract($contract->id)->get() as $collection) {
                    if ($collection->status === 'pending' || $collection->status === 'partial') {
                        $collection->update([
                            'status'           => 'written_off',
                            'is_written_off'   => true,
                            'written_off_date' => now(),
                        ]);
                    }
                }
            }

            return response()->json([
                'message' => 'Contract services cancelled successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to cancel contract services',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a single service on a contract — requires contracts.cancel permission.
     */
    public function cancelSingleService(Contract $contract, $service_slug)
    {
        // Ownership check: own-scoped users may only cancel services on their own contracts
        $this->authorizeRecordAccess($contract, 'contracts', 'update', 'employee_id');

        try {
            $service = $contract->services()->where('slug', $service_slug)->firstOrFail();

            // Calculate service total price from pivot
            $pivot             = $service->pivot;
            $serviceTotalPrice = ($pivot->unit_price * $pivot->quantity) - $pivot->discount;

            // Calculate total amount collected for this service
            $totalCollected = $service->collectionsForContract($contract->id)
                ->get()
                ->sum('amount_collected');

            // Update contract amount: deduct service price, add back any collected amount
            $contract->update([
                'amount'      => $contract->amount - $serviceTotalPrice + $totalCollected,
                'amount_paid' => $contract->amount_paid - $totalCollected,
            ]);

            $contract->services()->updateExistingPivot($service->id, [
                'status'         => 'cancelled',
                'is_cancelled'   => true,
                'cancelled_date' => now(),
            ]);

            foreach ($service->collectionsForContract($contract->id)->get() as $collection) {
                if ($collection->status === 'pending' || $collection->status === 'partial') {
                    $collection->update(['status' => 'written_off']);
                }
            }

            return response()->json([
                'message' => 'Service cancelled successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to cancel service',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eager-load contract relations with service collections scoped to each contract.
     */
    private function contractRelationsForResource(): array
    {
        return [
            'client',
            'employee',
            'collections',
            'layoutAnswers',
            'layoutAnswers.layoutField.layout',
            'services.collections',
        ];
    }
}
