<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use Illuminate\Http\Request;
use App\Models\Collection;
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

            $query = Collection::with(['contract.employee', 'client']);
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
            return response()->json($collections, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve collections: ' . $e->getMessage()], 500);
        }
    }

    public function store(StoreCollectionRequest $request){
        try {
            $collection = Collection::create($request->validated());
            return response()->json(new CollectionResource($collection), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create collection: ' . $e->getMessage()], 500);
        }
    }
    public function show($id){
        try {
            $collection = Collection::with(['contract', 'client'])->findOrFail($id);
            return response()->json(new CollectionResource($collection), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve collection: ' . $e->getMessage()], 500);
        }
    }

    public function update(UpdateCollectionRequest $request, $id){
        try {
            $collection = Collection::findOrFail($id);
            $collection->update($request->validated());
            return response()->json(new CollectionResource($collection), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update collection: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id){
        try {
            $collection = Collection::findOrFail($id);
            $collection->delete();
            return response()->json(['message' => 'Collection deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete collection: ' . $e->getMessage()], 500);
        }
    }
}
