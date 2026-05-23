<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    public function index()
    {
        try {
            // Logic to retrieve follow-up data
            $followUps = FollowUp::with(['lead', 'employee'])->get();

            return response()->json([
                'success' => true,
                'data' => $followUps
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve follow-ups',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|string',
            'status' => 'required|string',
            'follow_up_date' => 'required|date',
            'next_follow_up_date' => 'nullable|date|after:follow_up_date',
            'notes' => 'nullable|string'
        ]);

        try {
            // Logic to create a new follow-up
            $followUp = FollowUp::create([
                'lead_id' => $request->lead_id,
                'employee_id' => $request->employee_id,
                'type' => $request->type,
                'status' => $request->status,
                'follow_up_date' => $request->follow_up_date,
                'next_follow_up_date' => $request->next_follow_up_date,
                'notes' => $request->notes
            ]);

            return response()->json([
                'success' => true,
                'data' => $followUp
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create follow-up',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            // Logic to retrieve a specific follow-up
            $followUp = FollowUp::with(['lead', 'employee'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $followUp
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve follow-up',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'lead_id' => 'sometimes|required|exists:leads,id',
            'employee_id' => 'sometimes|required|exists:employees,id',
            'type' => 'sometimes|required|string',
            'status' => 'sometimes|required|string',
            'follow_up_date' => 'sometimes|required|date',
            'next_follow_up_date' => 'nullable|date|after:follow_up_date',
            'notes' => 'nullable|string'
        ]);

        try {
            // Logic to update a specific follow-up
            $followUp = FollowUp::findOrFail($id);
            $followUp->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $followUp
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update follow-up',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Logic to delete a specific follow-up
            $followUp = FollowUp::findOrFail($id);
            $followUp->delete();

            return response()->json([
                'success' => true,
                'message' => 'Follow-up deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete follow-up',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
