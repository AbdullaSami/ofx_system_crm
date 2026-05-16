<?php

namespace App\Http\Controllers\v1;

use App\Models\Service;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ServiceController extends Controller
{
    public function index()
    {
        try {
            $services = Service::all();
            return response()->json($services);
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
                'department_id' => 'required|exists:departments,id',
                // Layout
                'layout.label' => 'nullable|string',
                'layout.is_active' => 'boolean',
                'layout.is_default' => 'boolean',
                'layout.version' => 'nullable|string',
                'layout.description' => 'nullable|string',
                'layout.sort_order' => 'integer',
                // Layout fields
                'layout' => 'nullable|array',
                'layout.*.field_name' => 'required|string',
                'layout.*.field_type' => 'required|in:text,number,email,date,select,checkbox,textarea,file',
                'layout.*.is_required' => 'boolean',
                'layout.*.sort_order' => 'integer',
                'layout.*.default_value' => 'nullable|string',
                'layout.*.validation_rules' => 'nullable|string',
                'layout.*.options' => 'nullable|array',
                'layout.*.placeholder' => 'nullable|string',
                'layout.*.help_text' => 'nullable|string',
            ]);

            $service = Service::create(
                array_diff_key($validated, array_flip(['layout']))
            );

            if (isset($validated['layout']) && !empty($validated['layout'])) {
                $service->layouts()->create($validated['layout']);
            }

            return response()->json($service);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($service)
    {
        try {
            $service = Service::with(['layouts.layoutFields', 'department'])->where('slug', $service)->first();
            if (!$service) {
                return response()->json(['message' => 'Service not found'], 404);
            }
            return response()->json($service);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $service)
    {
        try {
            $service = Service::where('slug', $service)->first();
            if (!$service) {
                return response()->json(['message' => 'Service not found'], 404);
            }
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'department_id' => 'required|exists:departments,id',
                // Layout
                'layout.label' => 'nullable|string',
                'layout.is_active' => 'boolean',
                'layout.is_default' => 'boolean',
                'layout.version' => 'nullable|string',
                'layout.description' => 'nullable|string',
                'layout.sort_order' => 'integer',
                // Layout fields
                'layout' => 'nullable|array',
                'layout.*.field_name' => 'required|string',
                'layout.*.field_type' => 'required|in:text,number,email,date,select,checkbox,textarea,file',
                'layout.*.is_required' => 'boolean',
                'layout.*.sort_order' => 'integer',
                'layout.*.default_value' => 'nullable|string',
                'layout.*.validation_rules' => 'nullable|string',
                'layout.*.options' => 'nullable|array',
                'layout.*.placeholder' => 'nullable|string',
                'layout.*.help_text' => 'nullable|string',
            ]);
            $service->update(
                array_diff_key($validated, array_flip(['layout']))
            );
            if (isset($validated['layout']) && !empty($validated['layout'])) {
                $service->layouts()->update($validated['layout']);
            }
            return response()->json($service);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($service)
    {
        try {
            $service = Service::where('slug', $service)->first();
            if (!$service) {
                return response()->json(['message' => 'Service not found'], 404);
            }
            $service->delete();
            return response()->json(['message' => 'Service deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }
}
