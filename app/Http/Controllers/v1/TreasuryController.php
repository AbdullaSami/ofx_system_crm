<?php

namespace App\Http\Controllers\v1;

use Illuminate\Routing\Controller as BaseController;

use Illuminate\Http\Request;
use App\Models\TreasuryAccount;
class TreasuryController extends BaseController
{
    public function __construct() {
        $this->middleware('permission:treasury.viewAny')->only('index');
        $this->middleware('permission:treasury.view')->only('show');
        $this->middleware('permission:treasury.create')->only('store');
        $this->middleware('permission:treasury.update')->only('update');
        $this->middleware('permission:treasury.delete')->only('destroy');
    }

    public function index()
    {
        try {
            $treasuries = TreasuryAccount::all();
            return response()->json($treasuries);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $treasury = TreasuryAccount::create($request->all());
            return response()->json($treasury, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $treasury = TreasuryAccount::with('transactions')->findOrFail($id);
            return response()->json($treasury);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
        $treasury = TreasuryAccount::findOrFail($id);
        $treasury->update($request->all());
        return response()->json($treasury);
    } catch (\Exception $e) {
        return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
    }
    }

    public function destroy($id)
    {
        try {
            $treasury = TreasuryAccount::findOrFail($id);
            $treasury->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }
}
