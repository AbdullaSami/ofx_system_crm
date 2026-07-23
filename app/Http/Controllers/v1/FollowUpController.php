<?php

namespace App\Http\Controllers\v1;

use Illuminate\Routing\Controller as BaseController;
use App\Models\FollowUp;
use App\Http\Concerns\AuthorizesScope;
use Illuminate\Http\Request;

class FollowUpController extends BaseController
{
    use AuthorizesScope;

    public function __construct()
    {
        $this->middleware('permission:follow-ups.view|follow-ups.view.own')->only('index');
        $this->middleware('permission:follow-ups.view|follow-ups.view.own')->only('show');
        $this->middleware('permission:follow-ups.create')->only('store');
        $this->middleware('permission:follow-ups.update|follow-ups.update.own')->only('update');
        $this->middleware('permission:follow-ups.delete|follow-ups.delete.own')->only('destroy');
    }

    public function index()
    {
        try {
            $followUps = FollowUp::query()
                ->visibleTo(auth()->user())  // Data-scope based on permission
                ->with(['lead', 'employee'])
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $followUps,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve follow-ups',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'lead_id'            => 'required|exists:leads,id',
            'employee_id'        => 'required|exists:employees,id',
            'type'               => 'required|string',
            'status'             => 'required|string',
            'follow_up_date'     => 'required|date',
            'next_follow_up_date'=> 'nullable|date|after:follow_up_date',
            'notes'              => 'nullable|string',
        ]);

        try {
            $followUp = FollowUp::create([
                'lead_id'             => $request->lead_id,
                'employee_id'         => $request->employee_id,
                'type'                => $request->type,
                'status'              => $request->status,
                'follow_up_date'      => $request->follow_up_date,
                'next_follow_up_date' => $request->next_follow_up_date,
                'notes'               => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $followUp,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create follow-up',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $followUp = FollowUp::with(['lead', 'employee'])->findOrFail($id);

            // Ownership check: own-scoped users may only view their own follow-ups
            $this->authorizeRecordAccess($followUp, 'follow-ups', 'view', 'employee_id');

            return response()->json([
                'success' => true,
                'data'    => $followUp,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve follow-up',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'lead_id'             => 'sometimes|required|exists:leads,id',
            'employee_id'         => 'sometimes|required|exists:employees,id',
            'type'                => 'sometimes|required|string',
            'status'              => 'sometimes|required|string',
            'follow_up_date'      => 'sometimes|required|date',
            'next_follow_up_date' => 'nullable|date|after:follow_up_date',
            'notes'               => 'nullable|string',
        ]);

        try {
            $followUp = FollowUp::findOrFail($id);

            // Ownership check: own-scoped users may only update their own follow-ups
            $this->authorizeRecordAccess($followUp, 'follow-ups', 'update', 'employee_id');

            $followUp->update($request->all());

            return response()->json([
                'success' => true,
                'data'    => $followUp,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update follow-up',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $followUp = FollowUp::findOrFail($id);

            // Ownership check: own-scoped users may only delete their own follow-ups
            $this->authorizeRecordAccess($followUp, 'follow-ups', 'delete', 'employee_id');

            $followUp->delete();

            return response()->json([
                'success' => true,
                'message' => 'Follow-up deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete follow-up',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
