<?php

namespace App\Http\Controllers\v1;

use Illuminate\Routing\Controller as BaseController;
use App\Models\Service;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends BaseController
{
    public function __construct() {
        $this->middleware('permission:teams.view|teams.view.own')->only('index');
        $this->middleware('permission:teams.view|teams.view.own')->only('show');
        $this->middleware('permission:teams.create')->only('store');
        $this->middleware('permission:teams.update|teams.update.own')->only('update');
        $this->middleware('permission:teams.delete|teams.delete.own')->only('destroy');
    }

    public function index()
    {
        try {
            $teams = Team::with('services')->get();

            return response()->json($teams);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validate = $request->validate([
                'name' => 'required|string|max:255',
                'service_slugs' => 'required|array',
                'service_slugs.*' => 'exists:services,slug',
            ]);
            $team = Team::create([
                'name' => $validate['name'],
            ]);
            if ($validate['service_slugs']) {
                $services = Service::whereIn('slug', $validate['service_slugs'])->get();
                $pivotData = [];
                foreach ($services as $service) {
                    $pivotData[$service->id] = ['department_id' => $service->department_id];
                }
                $team->services()->attach($pivotData);
            }

            return response()->json($team, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($team)
    {
        try {
            $team = Team::with(['employees', 'lead', 'owner', 'services', 'departments'])->where('slug', $team)->first();

            return response()->json($team);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Team $team)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'service_slugs' => 'sometimes|array',
                'service_slugs.*' => 'exists:services,slug',
            ]);

            if (isset($validated['name'])) {
                $team->update(['name' => $validated['name']]);
            }

            if (isset($validated['service_slugs'])) {
                $services = Service::whereIn('slug', $validated['service_slugs'])->get();

                $pivotData = [];
                foreach ($services as $service) {
                    $pivotData[$service->id] = ['department_id' => $service->department_id];
                }

                $team->services()->sync($pivotData);
            }

            return response()->json($team->load('services'), 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($team)
    {
        try {
            $team = Team::where('slug', $team)->first();
            $team->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }
}
