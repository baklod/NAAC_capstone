<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->with('employee')
            ->latest()
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name ?? $user->user_name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'staff',
                    'profile_picture' => $user->employee?->profile_picture,
                    'is_active' => (bool) ($user->is_active ?? true),
                    'is_online' => (bool) ($user->is_online ?? false),
                    'created_at' => $user->created_at,
                ];
            });

        return response()->json(['data' => $users]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'max:50'],
            'profile_picture' => ['nullable', 'image', 'max:4096'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ];

        if (Schema::hasColumn('users', 'user_name')) {
            $attributes['user_name'] = $validated['name'];
        }

        if (Schema::hasColumn('users', 'is_active')) {
            $attributes['is_active'] = true;
        }

        if (Schema::hasColumn('users', 'is_online')) {
            $attributes['is_online'] = false;
        }

        if (Schema::hasColumn('users', 'employee_id')) {
            $seed = (string) now()->timestamp . random_int(1000, 9999);
            $profilePictureUrl = null;

            if ($request->hasFile('profile_picture')) {
                $path = $request->file('profile_picture')->store('employees', 'public');
                $profilePictureUrl = Storage::url($path);
            }

            $employee = Employee::create([
                'first_name' => $validated['name'],
                'last_name' => null,
                'contact_number' => (int) substr($seed, -9),
                'contact_email' => $validated['email'],
                'address' => 'N/A-' . $seed,
                'profile_picture' => $profilePictureUrl,
            ]);

            $attributes['employee_id'] = $employee->id;
        }

        $user = User::create($attributes);
    $user->load('employee');

        return response()->json([
            'message' => 'User created successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name ?? $user->user_name,
                'email' => $user->email,
                'role' => $user->role ?? 'staff',
                'profile_picture' => $user->employee?->profile_picture,
                'is_active' => (bool) ($user->is_active ?? true),
                'is_online' => (bool) ($user->is_online ?? false),
                'created_at' => $user->created_at,
            ],
        ], 201);
    }
}
