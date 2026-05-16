<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Team;
class TeamController extends Controller
{
    public function index()
    {
        try {
            $teams = Team::all();
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
                $team->services()->attach($validate['service_id']);
            }
            return response()->json($team, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Team $team)
    {
        try {
            return response()->json($team);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Team $team)
    {
        try {
            $team->update($request->all());
            return response()->json($team);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Team $team)
    {
        try {
            $team->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }
}
