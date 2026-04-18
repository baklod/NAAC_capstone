<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::latest()->get();

        return response()->json(['data' => $branches]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('branches', 'name')],
            'location' => ['required', 'string', 'max:255'],
            'manager' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive', 'planned'])],
        ]);

        $branch = Branch::create($validated);

        return response()->json([
            'message' => 'Branch created successfully.',
            'data' => $branch,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $branch = Branch::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('branches', 'name')->ignore($branch->id)],
            'location' => ['required', 'string', 'max:255'],
            'manager' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive', 'planned'])],
        ]);

        $branch->update($validated);

        return response()->json([
            'message' => 'Branch updated successfully.',
            'data' => $branch,
        ]);
    }

    public function destroy(int $id)
    {
        $branch = Branch::findOrFail($id);
        $branch->delete();

        return response()->json([
            'message' => 'Branch deleted successfully.',
        ]);
    }
}
