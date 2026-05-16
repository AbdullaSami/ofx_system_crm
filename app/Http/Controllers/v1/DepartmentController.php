<?php

namespace App\Http\Controllers\v1;

use App\Models\Department;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DepartmentController extends Controller
{
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
            $department = Department::where('slug', $department)->first();
            $department->update($request->all());
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
