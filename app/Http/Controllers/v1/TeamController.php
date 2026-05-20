<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Team;
use App\Models\Service;
class TeamController extends Controller
{
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
                'service_id' => 'required|array',
                'service_id.*' => 'exists:services,id',
            ]);
            $team = Team::create([
                'name' => $validate['name'],
            ]);
            if ($validate['service_id']) {
                $services = Service::whereIn('id', $validate['service_id'])->get();
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
            $team = Team::where('slug', $team)->first();
            return response()->json($team);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $team)
    {
        try {
            $team = Team::where('slug', $team)->first();
            $team->update($request->all());
            return response()->json($team);
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
