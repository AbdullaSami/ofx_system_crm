<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\Collection;
use App\Models\Service;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\UpdateCollectionRequest;
class CollectionController extends Controller
{
    public function index(Request $request){
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

    public function store(StoreCollectionRequest $request){
        try {
            $validedData = $request->validated();
$collection = Collection::create(
    Arr::except($validatedData, ['services'])
);            if ($request->has('service_slug')) {
                    $service = Service::where('slug', $validedData['service_slug'])->first();
                $collection->services()->attach($service->id); // Sync with the new service ID or detach if not found
            }
            return response()->json(CollectionResource::make($collection), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create collection: ' . $e->getMessage()], 500);
        }
    }
    public function show($id){
        try {
            $collection = Collection::with(['contract', 'client', 'services'])->findOrFail($id);
            return response()->json(CollectionResource::make($collection), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve collection: ' . $e->getMessage()], 500);
        }
    }

    public function update(UpdateCollectionRequest $request, $id){
        try {
            $collection = Collection::findOrFail($id);
            $validedData = $request->validated();
            $collection->update($validedData->except('services'));
            if ($request->has('service_slug')) {
                    $service = Service::where('slug', $validedData['service_slug'])->first();
                $collection->services()->sync($service->id); // Sync with the new service ID or detach if not found
            }
            return response()->json(CollectionResource::make($collection), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update collection: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id){
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
