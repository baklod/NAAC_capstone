<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $inventories = Inventory::with('product')->latest()->get();

        return response()->json(['data' => $inventories]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $inventory = Inventory::updateOrCreate(
            ['product_id' => $validated['product_id']],
            [
                'quantity' => $validated['quantity'],
                'status' => $validated['status'],
            ]
        );

        return response()->json([
            'message' => 'Inventory saved successfully.',
            'data' => $inventory->load('product'),
        ], 201);
    }
}
