<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use App\Models\Client;
class ClientController extends BaseController
{

    public function __construct() {
        $this->middleware('permission:clients.viewAny')->only('index');
        $this->middleware('permission:clients.view')->only('show');
        $this->middleware('permission:clients.create')->only('store');
        $this->middleware('permission:clients.update')->only('update');
        $this->middleware('permission:clients.delete')->only('destroy');
    }


    public function index(Request $request)
    {
        $search = $request->query('search');
        try {
        $clients = Client::query()
            ->when($search, function ($query, $search) {
                $query->where('client_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%")
                    ->orWhere('assigned_to', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");

            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return response()->json($clients);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch clients', 'message' => $e->getMessage()], 500);
        }
    }

    public function show(int $id)
    {
        try {
            $client = Client::with(['lead', 'assignedTo', 'contracts.collections', 'contracts.services', 'contracts.layoutAnswers'])->findOrFail($id);
            return response()->json($client);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch client', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'client_name' => 'required|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,archived',
            'lead_id' => 'nullable|exists:leads,id',
            'assigned_to' => 'nullable|exists:employees,id',
            'user_id' => 'nullable|exists:users,id',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        try {
            $client = Client::create($validatedData);
            return response()->json($client, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create client', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $validatedData = $request->validate([
            'client_name' => 'required|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,archived',
            'lead_id' => 'nullable|exists:leads,id',
            'assigned_to' => 'nullable|exists:employees,id',
            'user_id' => 'nullable|exists:users,id',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        try {
            $client = Client::findOrFail($id);
            $client->update($validatedData);
            return response()->json($client);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update client', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $client = Client::findOrFail($id);
            $client->delete();
            return response()->json(['message' => 'Client deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete client', 'message' => $e->getMessage()], 500);
        }
    }
}
