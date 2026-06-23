<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Models\Collection;
use App\Models\Service;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Http\Services\TreasuryAccountingService;
use App\Models\TreasuryAccount;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        try {

            $search = $request->query('search');
            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            $employeeId = $request->query('employee_id');
            $status = $request->query('status');

            $query = Collection::with(['contract.employee', 'client', 'services']);
            if ($search) {
                $query->where('amount', 'like', "%{$search}%");
            }
            if ($dateFrom && $dateTo) {
                $query->whereBetween('collection_date', [$dateFrom, $dateTo]);
            }
            if ($employeeId) {
                $query->where(fn($q) => $q->whereHas('contract', fn($q) => $q->where('employee_id', $employeeId)));
            }
            if ($status) {
                $query->where('status', $status);
            }
            $collections = $query->get();
            return response()->json(CollectionResource::collection($collections), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve collections: ' . $e->getMessage()], 500);
        }
    }

    public function store(StoreCollectionRequest $request)
    {
        try {
            DB::beginTransaction();
            $validatedData = $request->validated();

            if (Collection::exceedsContractAmount(
                $validatedData['contract_id'],
                $validatedData['amount_due'],
                'amount_due'
            )) {
                return response()->json([
                    'message' => 'Total amount due exceeds the contract amount.'
                ], 422);
            }

            $collection = Collection::create(
                Arr::except($validatedData, ['services'])
            );
            if ($request->has('service_slug')) {
                $service = Service::where('slug', $validatedData['service_slug'])->first();
                $collection->services()->attach($service); // Sync with the new service ID or detach if not found
            }

            $account = TreasuryAccount::where(
                'account_name',
                $collection->payment_method
            )->firstOrFail();
            if ($collection->amount_collected > 0) {
                $collection->contract()->increment('amount_paid', $collection->amount_collected);
                (new TreasuryAccountingService())->recordTransaction($account->id, $collection->amount_collected, 'credit');
            }
            DB::commit();
            return response()->json(CollectionResource::make($collection), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create collection: ' . $e->getMessage()], 500);
        }
    }
    public function show($id)
    {
        try {
            $collection = Collection::with(['contract', 'client', 'services'])->findOrFail($id);
            return response()->json(CollectionResource::make($collection), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve collection: ' . $e->getMessage()], 500);
        }
    }

    public function update(UpdateCollectionRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $collection = Collection::with('contract')->findOrFail($id);

            $oldCollected = $collection->amount_collected;

            $data = Arr::except($request->validated(), ['service_slug']);

            if (Collection::exceedsContractAmount(
                $data['contract_id'],
                $data['amount_collected'],
                'amount_collected',
                $collection->id
            )) {
                return response()->json([
                    'message' => 'Total collected amount exceeds the contract amount.'
                ], 422);
            }

            if ($data['amount_collected'] > $collection->amount_due) {
                return response()->json('error: the collected amount cant be more the amount due collection');
            }

            if (($data['status'] ?? $collection->status) === 'paid') {
                $data['amount_collected'] = $collection->amount_due;
                $data['collection_date'] = now();
            }

            $collection->update($data);

            if (!empty($request->service_slug)) {

                $service = Service::where(
                    'slug',
                    $request->service_slug
                )->firstOrFail();

                $collection->services()->sync([$service->id]);
            }

            $collection->refresh();

            $difference = $collection->amount_collected - $oldCollected;

            if ($difference != 0) {

                $collection->contract()->increment(
                    'amount_paid',
                    $difference
                );

                $account = TreasuryAccount::where(
                    'account_name',
                    $collection->payment_method
                )->firstOrFail();

                $type = $difference > 0 ? 'credit' : 'credit';

                (new TreasuryAccountingService())->recordTransaction(
                    $account->id,
                    abs($difference),
                    $type,
                    'Collection update for contract #' .
                        $collection->contract->contract_number
                );
            }

            DB::commit();

            return response()->json(
                CollectionResource::make($collection->fresh()),
                200
            );
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Failed to update collection.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $collection = Collection::findOrFail($id);
            $collection->update(['status' => 'written_off']);
            $collection->delete();
            return response()->json(['message' => 'Collection deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete collection: ' . $e->getMessage()], 500);
        }
    }
}
