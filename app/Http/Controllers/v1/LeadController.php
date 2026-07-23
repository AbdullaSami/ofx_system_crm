<?php

namespace App\Http\Controllers\v1;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\Models\Lead;
use App\Http\Concerns\AuthorizesScope;

class LeadController extends BaseController
{
    use AuthorizesScope;

    public function __construct()
    {
        $this->middleware('permission:leads.view|leads.view.own')->only('index');
        $this->middleware('permission:leads.view|leads.view.own')->only('show');
        $this->middleware('permission:leads.create')->only('store');
        $this->middleware('permission:leads.update|leads.update.own')->only('update');
        $this->middleware('permission:leads.delete|leads.delete.own')->only('destroy');
    }

    public function index()
    {
        try {
            $leads = Lead::query()
                ->visibleTo(auth()->user())  // Data-scope based on permission
                ->with(['assignedTo', 'followUps'])
                ->get();

            return response()->json(['message' => 'Leads fetched successfully', 'data' => $leads], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch leads', 'details' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'lead_name'       => 'required|string|max:255',
            'email'           => 'nullable|email|unique:leads,email',
            'phone'           => 'nullable|string|max:20',
            'company'         => 'nullable|string|max:255',
            'source'          => 'nullable|string|max:255',
            'status'          => 'nullable|string|max:255',
            'assigned_to'     => 'nullable|integer|exists:employees,id',
            'estimated_value' => 'nullable|numeric',
            'follow_up_date'  => 'nullable|date',
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

            // Ownership check: own-scoped users may only view their own leads
            $this->authorizeRecordAccess($lead, 'leads', 'view', 'assigned_to');

            return response()->json(['message' => 'Lead fetched successfully', 'data' => $lead], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch lead', 'details' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'lead_name'       => 'sometimes|required|string|max:255',
            'email'           => 'sometimes|nullable|email|unique:leads,email,' . $id,
            'phone'           => 'sometimes|nullable|string|max:20',
            'company'         => 'sometimes|nullable|string|max:255',
            'source'          => 'sometimes|nullable|string|max:255',
            'status'          => 'sometimes|nullable|string|max:255',
            'assigned_to'     => 'sometimes|nullable|integer|exists:employees,id',
            'estimated_value' => 'sometimes|nullable|numeric',
            'follow_up_date'  => 'sometimes|nullable|date',
        ]);

        try {
            $lead = Lead::findOrFail($id);

            // Ownership check: own-scoped users may only update their own leads
            $this->authorizeRecordAccess($lead, 'leads', 'update', 'assigned_to');

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

            // Ownership check: own-scoped users may only delete their own leads
            $this->authorizeRecordAccess($lead, 'leads', 'delete', 'assigned_to');

            $lead->delete();
            return response()->json(['message' => 'Lead deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete lead', 'details' => $e->getMessage()], 500);
        }
    }
}
