<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPurchaseManagement;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\ManagerBranchScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    use AuthorizesPurchaseManagement;

    public function index(Request $request)
    {
        $branches = ManagerBranchScope::scopeBranches(
            Branch::with('managerUser:id,name,user_name,email,role'),
            $request->user(),
        )
            ->latest()
            ->get();

        return response()->json(['data' => $branches]);
    }

    public function managerOptions()
    {
        $managers = User::query()
            ->where('role', 'manager')
            ->orderBy('name')
            ->orderBy('user_name')
            ->get(['id', 'name', 'user_name', 'email', 'role']);

        return response()->json(['data' => $managers]);
    }

    public function store(Request $request)
    {
        $this->assertAdminOnly('Only administrators can manage branches.');

        $validated = $this->validateBranch($request);

        $branch = Branch::create($validated);

        return response()->json([
            'message' => 'Branch created successfully.',
            'data' => $branch->load('managerUser:id,name,user_name,email,role'),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->assertAdminOnly('Only administrators can manage branches.');

        $branch = Branch::findOrFail($id);

        $validated = $this->validateBranch($request, $branch->id);

        $branch->update($validated);

        return response()->json([
            'message' => 'Branch updated successfully.',
            'data' => $branch->load('managerUser:id,name,user_name,email,role'),
        ]);
    }

    public function destroy(int $id)
    {
        $this->assertAdminOnly('Only administrators can manage branches.');

        $branch = Branch::findOrFail($id);
        $branch->delete();

        return response()->json([
            'message' => 'Branch deleted successfully.',
        ]);
    }

    private function validateBranch(Request $request, ?int $branchId = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'name')->ignore($branchId),
            ],
            'location' => ['required', 'string', 'max:255'],
            'manager_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'manager')),
            ],
            'status' => ['required', Rule::in(['active', 'inactive', 'planned'])],
        ]);

        $validated['manager'] = $this->resolveManagerLabel($validated['manager_user_id'] ?? null);

        return $validated;
    }

    private function resolveManagerLabel(?int $managerUserId): ?string
    {
        if (!$managerUserId) {
            return null;
        }

        $user = User::find($managerUserId);

        if (!$user) {
            return null;
        }

        return $user->name ?? $user->user_name ?? $user->email;
    }
}
