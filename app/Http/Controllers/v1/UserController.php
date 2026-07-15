<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get all users, optionally searched by name.
     */
    public function index(Request $request)
    {
        $query = User::query();

        $query->when($request->input('search'), function ($q, $search) {
            $q->where('name', 'like', "%{$search}%");
        });

        $users = $query->latest('id')->paginate($request->input('per_page', 15));

        return response()->json($users);
    }

    /**
     * Store a new user with credentials.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ])->validate();

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'data'    => $user,
        ], 201);
    }

    /**
     * Show a single user.
     */
    public function show(User $user)
    {
        return response()->json($user);
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, User $user)
    {
        $validated = Validator::make($request->all(), [
            'name'     => 'sometimes|required|string|max:255',
            'email'    => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'sometimes|required|string|min:8|confirmed',
        ])->validate();

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'data'    => $user,
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}
