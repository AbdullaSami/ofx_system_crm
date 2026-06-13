<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\TreasuryAccount;
class TreasuryController extends Controller
{
    public function index()
    {
        $treasuries = TreasuryAccount::all();
        return response()->json($treasuries);
    }

    public function store(Request $request)
    {
        $treasury = TreasuryAccount::create($request->all());
        return response()->json($treasury, 201);
    }

    public function show($id)
    {
        $treasury = TreasuryAccount::with('transactions')->findOrFail($id);
        return response()->json($treasury);
    }

    public function update(Request $request, $id)
    {
        $treasury = TreasuryAccount::findOrFail($id);
        $treasury->update($request->all());
        return response()->json($treasury);
    }

    public function destroy($id)
    {
        $treasury = TreasuryAccount::findOrFail($id);
        $treasury->delete();
        return response()->json(null, 204);
    }
}
