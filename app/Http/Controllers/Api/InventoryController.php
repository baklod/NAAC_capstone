<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPurchaseManagement;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryRevenueLog;
use App\Services\ManagerBranchScope;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use AuthorizesPurchaseManagement;
    public function index(Request $request)
    {
        $inventories = ManagerBranchScope::scopeInventories(
            Inventory::with([
                'product',
                'branch:id,name,location',
            ]),
            $request->user(),
        )
            ->latest()
            ->get();

        return response()->json(['data' => $inventories]);
    }

    public function revenueLogs(Request $request)
    {
        $logs = ManagerBranchScope::scopeInventories(
            InventoryRevenueLog::with([
                'branch:id,name,location',
                'product:id,name,unit,price',
            ]),
            $request->user(),
        )
            ->latest()
            ->get();

        return response()->json(['data' => $logs]);
    }

    public function store(Request $request)
    {
        $this->assertManagerOrAdmin(
            'Unauthorized. Only managers and admins can add inventory adjustments.',
        );

        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $status = (int) $validated['quantity'] === 0 ? 'out_of_stock' : 'in_stock';

        if (!ManagerBranchScope::ensureBranchAllowed($request->user(), (int) $validated['branch_id'])) {
            return response()->json([
                'message' => 'You can only manage inventory for your assigned branch.',
            ], 403);
        }

        $inventory = Inventory::create([
            'batch_number' => $this->generateBatchNumber(
                (int) $validated['branch_id'],
                (int) $validated['product_id'],
            ),
            'branch_id' => $validated['branch_id'],
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'],
            'status' => $status,
        ]);

        $this->logRevenue($inventory, 'created');

        return response()->json([
            'message' => 'Inventory saved successfully.',
            'data' => $inventory->load(['product', 'branch:id,name,location']),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $inventory = Inventory::findOrFail($id);

        if (!ManagerBranchScope::ensureBranchAllowed($request->user(), (int) $inventory->branch_id)) {
            return response()->json([
                'message' => 'You can only manage inventory for your assigned branch.',
            ], 403);
        }

        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        if (
            $inventory->branch_id !== (int) $validated['branch_id'] ||
            $inventory->product_id !== (int) $validated['product_id']
        ) {
            $validated['batch_number'] = $this->generateBatchNumber(
                (int) $validated['branch_id'],
                (int) $validated['product_id'],
            );
        }

        $validated['status'] = (int) $validated['quantity'] === 0
            ? 'out_of_stock'
            : 'in_stock';

        $inventory->update($validated);

        return response()->json([
            'message' => 'Inventory updated successfully.',
            'data' => $inventory->load(['product', 'branch:id,name,location']),
        ]);
    }

    public function destroy(int $id)
    {
        $inventory = Inventory::findOrFail($id);

        if (!ManagerBranchScope::ensureBranchAllowed(request()->user(), (int) $inventory->branch_id)) {
            return response()->json([
                'message' => 'You can only manage inventory for your assigned branch.',
            ], 403);
        }

        $inventory->delete();

        return response()->json([
            'message' => 'Inventory deleted successfully.',
        ]);
    }

    private function logRevenue(Inventory $inventory, string $action): void
    {
        $inventory->loadMissing('product');

        $price = (float) ($inventory->product?->price ?? 0);
        $quantity = (int) ($inventory->quantity ?? 0);

        InventoryRevenueLog::create([
            'inventory_id' => $inventory->id,
            'branch_id' => $inventory->branch_id,
            'product_id' => $inventory->product_id,
            'batch_number' => $inventory->batch_number,
            'quantity' => $quantity,
            'price' => $price,
            'expected_revenue' => $price * $quantity,
            'action' => $action,
        ]);
    }

    private function generateBatchNumber(int $branchId, int $productId): string
    {
        $date = now()->format('Ymd');
        $branchCode = 'BR-' . str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $productCode = 'PR-' . str_pad((string) $productId, 4, '0', STR_PAD_LEFT);
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $numbers = '0123456789';

        do {
            $suffix =
                $letters[random_int(0, strlen($letters) - 1)] .
                $numbers[random_int(0, strlen($numbers) - 1)] .
                $numbers[random_int(0, strlen($numbers) - 1)];
            $batchNumber = $date . '-' . $branchCode . '-' . $productCode . '-' . $suffix;
        } while (Inventory::where('batch_number', $batchNumber)->exists());

        return $batchNumber;
    }
}
