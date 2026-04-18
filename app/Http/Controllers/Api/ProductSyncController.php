<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Carbon;

class ProductSyncController extends Controller
{
    public function sync(Request $request)
    {
        $validated = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*.name' => ['required', 'string'],
            'products.*.unit' => ['nullable', 'string'],
            'products.*.price' => ['nullable', 'numeric'],
            'products.*.description' => ['nullable', 'string'],
            'products.*.processed_at' => ['nullable', 'date'], // from Flutter
        ]);

        try {
            $syncedCount = 0;

            foreach ($validated['products'] as $item) {
                $processedAt = !empty($item['processed_at'])
                    ? Carbon::parse($item['processed_at'])
                    : now();

                $product = Product::firstOrNew([
                    'name' => $item['name'],
                ]);

                $product->unit = $item['unit'] ?? 'pcs';
                $product->price = (float) ($item['price'] ?? 0);
                $product->description = $item['description'] ?? null;

                // keep original created_at for existing rows
                if (!$product->exists) {
                    $product->created_at = $processedAt;
                }

                // always set updated_at to actual processed time
                $product->updated_at = $processedAt;

                $product->save();
                $syncedCount++;
            }

            return response()->json([
                'status' => 'success',
                'message' => "Successfully synced {$syncedCount} product(s)",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}