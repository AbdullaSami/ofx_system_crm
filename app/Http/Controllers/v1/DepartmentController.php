<?php

namespace App\Http\Controllers\v1;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class DepartmentController extends BaseController
{
    
    public function __construct() {
        $this->middleware('permission:departments.view|departments.view.own')->only('index');
        $this->middleware('permission:departments.view|departments.view.own')->only('show');
        $this->middleware('permission:departments.create')->only('store');
        $this->middleware('permission:departments.update|departments.update.own')->only('update');
        $this->middleware('permission:departments.delete|departments.delete.own')->only('destroy');
    }

    public function index()
    {
        try {
            $departments = Department::all();
            return response()->json($departments);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);
            $departments = Department::create($validated);
            return response()->json($departments);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($department)
    {
        try {
            $department = Department::with(['services', 'teams', 'servicesViaTeams'])->where('slug', $department)->first();
            return response()->json($department);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $department)
    {
        try {
            $validated = $request->validate([
                'name'        => 'sometimes|string|max:255',
                'description' => 'nullable|string',
            ]);
            $department = Department::where('slug', $department)->firstOrFail();
            $department->update($validated);
            return response()->json($department);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($department)
    {
        try {
            $department = Department::where('slug', $department)->first();
            $department->delete();
            return response()->json(['message' => 'Department deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }
}
