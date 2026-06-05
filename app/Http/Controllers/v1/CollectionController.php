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
    public function index(){
        try {
            $collections = Collection::with(['contract', 'client'])->get();
            return response()->json(new CollectionResource($collections), 200);
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
