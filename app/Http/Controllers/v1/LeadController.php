<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
class LeadController extends Controller
{
    public function index()
    {
        try {
            $leads = Lead::with(['assignedTo', 'followUps'])->get();
            return response()->json(['message' => 'Leads fetched successfully', 'data' => $leads], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch leads', 'details' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'lead_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:leads,email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'assigned_to' => 'nullable|integer|exists:employees,id',
            'estimated_value' => 'nullable|numeric',
            'follow_up_date' => 'nullable|date',
        ]);

        try {
            $lead = Lead::create($request->all());
            return response()->json(['message' => 'Lead created successfully', 'data' => $lead], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create lead', 'details' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $lead = Lead::with(['assignedTo', 'followUps'])->findOrFail($id);
            return response()->json(['message' => 'Lead fetched successfully', 'data' => $lead], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch lead', 'details' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'lead_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|email|unique:leads,email,' . $id,
            'phone' => 'sometimes|nullable|string|max:20',
            'company' => 'sometimes|nullable|string|max:255',
            'source' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|nullable|string|max:255',
            'assigned_to' => 'sometimes|nullable|integer|exists:employees,id',
            'estimated_value' => 'sometimes|nullable|numeric',
            'follow_up_date' => 'sometimes|nullable|date',
        ]);

        try {
            $lead = Lead::findOrFail($id);
            $lead->update($request->all());
            return response()->json(['message' => 'Lead updated successfully', 'data' => $lead], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update lead', 'details' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $lead = Lead::findOrFail($id);
            $lead->delete();
            return response()->json(['message' => 'Lead deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete lead', 'details' => $e->getMessage()], 500);
        }
    }
}
