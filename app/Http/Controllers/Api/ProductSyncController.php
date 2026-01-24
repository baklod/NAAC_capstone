<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductSyncController extends Controller
{
    public function sync(Request $request)
    {
        $products = $request->input('products');

        if (!$products || !is_array($products)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No products data received'
            ], 400);
        }

        try {
            $syncedCount = 0;
            
            foreach ($products as $item) {
                Product::updateOrCreate(
                    ['barcode' => $item['barcode']],
                    [
                        'branch' => $item['branch'],
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                    ]
                );
                $syncedCount++;
            }

            return response()->json([
                'status' => 'success',
                'message' => "Successfully synced $syncedCount product(s)",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }
}