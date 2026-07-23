<?php

namespace App\Http\Controllers\v1;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\Models\TreasuryAccount;

class TreasuryController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:treasury.view|treasury.view.own')->only('index');
        $this->middleware('permission:treasury.view|treasury.view.own')->only('show');
        $this->middleware('permission:treasury.create')->only('store');
        $this->middleware('permission:treasury.update|treasury.update.own')->only('update');
        $this->middleware('permission:treasury.delete|treasury.delete.own')->only('destroy');
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
            $validated = $request->validate([
                'account_name' => 'required|string|max:255|unique:treasury_accounts,account_name',
                'balance'      => 'nullable|numeric|min:0',
                'currency'     => 'nullable|string|max:10',
                'description'  => 'nullable|string',
            ]);
            $treasury = TreasuryAccount::create($validated);
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
            $validated = $request->validate([
                'account_name' => 'sometimes|string|max:255|unique:treasury_accounts,account_name,' . $id,
                'balance'      => 'sometimes|numeric|min:0',
                'currency'     => 'nullable|string|max:10',
                'description'  => 'nullable|string',
            ]);
            $treasury = TreasuryAccount::findOrFail($id);
            $treasury->update($validated);
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
