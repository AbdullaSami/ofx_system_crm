<?php

namespace App\Http\Controllers\v1;

use Illuminate\Routing\Controller as BaseController;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends BaseController
{
    public function __construct() {
        $this->middleware('permission:users.viewAny')->only('index');
        $this->middleware('permission:users.view')->only('show');
        $this->middleware('permission:users.create')->only('store');
        $this->middleware('permission:users.update')->only('update');
        $this->middleware('permission:users.delete')->only('destroy');
    }
    
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
            'password' => 'required|string|min:8',
            'role'     => 'required|string',
            'permissions'=> 'array',
            'permissions.*'=> 'string',
        ])->validate();

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        if (isset($validated['role'])) {
            $user->assignRole($validated['role']);
        }

        if (isset($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        }

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
            'password' => 'sometimes|required|string|min:8',
            'role'     => 'sometimes|required|string',
            'permissions'=> 'array',
            'permissions.*'=> 'string',
        ])->validate();

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        if (isset($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
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
