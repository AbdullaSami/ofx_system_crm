<?php

namespace App\Http\Controllers\v1;

use App\Models\Service;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index()
    {
        try {
            $services = Service::with(['department'])->get();


            return response()->json(
                $services->map(function ($service) {
                    $arr = $service->toArray();
                    unset($arr['department_id'], $arr['id'], $arr['department']);
                    $arr['department_name'] = $service->department->name ?? null;
                    return $arr;
                })
            );
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function create($id)
    {
        $service = Service::with(['layouts', 'layouts.layoutFields'])->where('id', $id);
        return response()->json($service);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'department_id' => 'required|exists:departments,id',

                // Layout
                'layout' => 'nullable|array',

                'layout.label' => 'nullable|string|max:255',
                'layout.is_active' => 'nullable|boolean',
                'layout.is_default' => 'nullable|boolean',
                'layout.version' => 'nullable|string|max:50',
                'layout.description' => 'nullable|string',
                'layout.sort_order' => 'nullable|integer',

                // Layout fields
                'layout.fields' => 'nullable|array',

                'layout.fields.*.field_name' => 'required|string|max:255',
                'layout.fields.*.field_type' => 'required|in:text,number,email,date,select,checkbox,textarea,file',
                'layout.fields.*.is_required' => 'nullable|boolean',
                'layout.fields.*.sort_order' => 'nullable|integer',
                'layout.fields.*.default_value' => 'nullable|string',
                'layout.fields.*.validation_rules' => 'nullable|string',
                'layout.fields.*.options' => 'nullable|array',
                'layout.fields.*.placeholder' => 'nullable|string|max:255',
                'layout.fields.*.help_text' => 'nullable|string',
            ]);

            $service = Service::create(
                array_diff_key($validated, array_flip(['layout']))
            );

            if (isset($validated['layout']) && !empty($validated['layout'])) {
                $layout = $service->layouts()->create($validated['layout']);
                if (isset($validated['layout']['fields']) && !empty($validated['layout']['fields'])) {
                    foreach ($validated['layout']['fields'] as $field) {
                        // Encode array fields to JSON strings before persisting
                        if (isset($field['options']) && is_array($field['options'])) {
                            $field['options'] = json_encode($field['options']);
                        }

                        if (isset($field['validation_rules']) && is_array($field['validation_rules'])) {
                            $field['validation_rules'] = json_encode($field['validation_rules']);
                        }

                        $layout->layoutFields()->create($field);
                    }
                }
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
        DB::beginTransaction();

        try {

            $services = Service::where('slug', $service)->firstOrFail();

            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'department_id' => 'nullable|exists:departments,id',

                // Layout
                'layout' => 'nullable|array',
                'layout.label' => 'nullable|string|max:255',
                'layout.is_active' => 'nullable|boolean',
                'layout.is_default' => 'nullable|boolean',
                'layout.description' => 'nullable|string',
                'layout.sort_order' => 'nullable|integer',

                // Fields
                'layout.fields' => 'nullable|array',

                'layout.fields.*.field_name' => 'required|string|max:255',
                'layout.fields.*.field_type' => 'required|in:text,number,email,date,select,checkbox,textarea,file',
                'layout.fields.*.is_required' => 'nullable|boolean',
                'layout.fields.*.sort_order' => 'nullable|integer',
                'layout.fields.*.default_value' => 'nullable|string',
                'layout.fields.*.validation_rules' => 'nullable|string',
                'layout.fields.*.options' => 'nullable|array',
                'layout.fields.*.placeholder' => 'nullable|string|max:255',
                'layout.fields.*.help_text' => 'nullable|string',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Update Service
            |--------------------------------------------------------------------------
            */

            $services->update(
                collect($validated)
                    ->except('layout')
                    ->toArray()
            );

            /*
            |--------------------------------------------------------------------------
            | Create New Layout Version
            |--------------------------------------------------------------------------
            */

            if (!empty($validated['layout'])) {

                $layoutData = $validated['layout'];

                // If this layout will be the default, remove default from others
                if (($layoutData['is_default'] ?? false) === true) {
                    $services->layouts()->update([
                        'is_default' => false,
                    ]);
                }

                // If this layout will be active, deactivate previous layouts
                if (($layoutData['is_active'] ?? true) === true) {
                    $services->layouts()->update([
                        'is_active' => false,
                    ]);
                }

                // Get latest version
                $latestVersion = $services->layouts()->max('version');

                $newVersion = $latestVersion
                    ? ((float) $latestVersion + 1)
                    : 1.0;

                // Create new version
                $layout = $services->layouts()->create([
                    'label' => $layoutData['label'] ?? $services->name,
                    'is_active' => $layoutData['is_active'] ?? true,
                    'is_default' => $layoutData['is_default'] ?? false,
                    'version' => $newVersion,
                    'description' => $layoutData['description'] ?? null,
                    'sort_order' => $layoutData['sort_order'] ?? 0,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Create Layout Fields
                |--------------------------------------------------------------------------
                */

                if (!empty($layoutData['fields'])) {

                    foreach ($layoutData['fields'] as $field) {

                        $layout->layoutFields()->create([
                            'field_name' => $field['field_name'],
                            'field_type' => $field['field_type'],
                            'is_required' => $field['is_required'] ?? false,
                            'sort_order' => $field['sort_order'] ?? 0,
                            'default_value' => $field['default_value'] ?? null,
                            'validation_rules' => $field['validation_rules'] ?? null,
                            'options' => $field['options'] ?? null,
                            'placeholder' => $field['placeholder'] ?? null,
                            'help_text' => $field['help_text'] ?? null,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Service updated successfully',
                'data' => $services->fresh()->load([
                    'layouts' => fn($q) => $q->orderByDesc('version'),
                    'layouts.layoutFields',
                ]),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Service not found',
            ], 404);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
            ], 500);
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

    /**
     * Take array of id's and return layouts
     * @param Request $request
     * @return void
     */
    public function getServicesLayouts(Request $request)
    {
        $request->validate([
            'services_slugs' => 'required|array',
            'services_slugs.*' => 'string',
        ]);

        $services = Service::with('layouts.layoutFields')
            ->whereIn('slug', $request->services_slugs)
            ->get();

        return response()->json([
            'data' => $services,
        ]);
    }
}
