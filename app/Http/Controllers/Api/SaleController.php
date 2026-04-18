<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::with([
            'product',
            'processedBy:id,name,user_name,email',
        ])->latest()->get();

        return response()->json(['data' => $sales]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateSalePayload($request);
        [$sale, $created] = $this->createSale($request, $validated);

        return response()->json([
            'message' => $created
                ? 'Sale processed successfully.'
                : 'Sale already processed.',
            'data' => $sale,
        ], $created ? 201 : 200);
    }

    public function storeFromFlutter(Request $request)
    {
        $validated = $this->validateSalePayload($request);
        [$sale, $created] = $this->createSale($request, $validated, true);

        return response()->json([
            'message' => $created
                ? 'Flutter sale saved successfully.'
                : 'Flutter sale already processed.',
            'sale_number' => $sale->sale_number,
            'data' => $sale,
        ], $created ? 201 : 200);
    }

    private function validateSalePayload(Request $request): array
    {
        return $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'processed_by_user_id' => ['nullable', 'exists:users,id'],
            'unit_type' => ['nullable', 'string', 'max:20'],
            'sale_number' => ['nullable', 'string', 'max:32'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'processed_at_utc' => ['nullable', 'date'],
            'processed_at' => ['nullable', 'date'],
            'sold_at' => ['nullable', 'date'],
            'created_at' => ['nullable', 'date'],
        ]);
    }

    private function createSale(Request $request, array $validated, bool $useClientTimestamp = false): array
    {
        $processedByUserId = $request->user()?->id ?? ($validated['processed_by_user_id'] ?? null);
        $idempotencyKey = isset($validated['idempotency_key'])
            ? trim((string) $validated['idempotency_key'])
            : null;
        $clientProcessedAt = $useClientTimestamp
            ? $this->resolveClientProcessedAt($validated)
            : null;
        $requestedSaleNumber = $this->resolveRequestedSaleNumber($validated);

        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        }

        $product = Product::findOrFail($validated['product_id']);
        $totalPrice = (float) $product->price * (int) $validated['quantity'];
        $unitType = $this->resolveUnitType(
            $validated['unit_type'] ?? null,
            $product->unit ?? null,
        );

        $attributes = [
            'product_id' => $product->id,
            'processed_by_user_id' => $processedByUserId,
            'unit_type' => $unitType,
            'quantity' => $validated['quantity'],
            'total_price' => $totalPrice,
        ];

        if ($requestedSaleNumber !== null) {
            $attributes['sale_number'] = $requestedSaleNumber;
        } elseif ($useClientTimestamp && $clientProcessedAt !== null) {
            $groupedSaleNumber = $this->findGroupedSaleNumber($processedByUserId, $clientProcessedAt);

            if ($groupedSaleNumber !== null) {
                $attributes['sale_number'] = $groupedSaleNumber;
            }
        }

        if ($idempotencyKey !== null) {
            $attributes['idempotency_key'] = $idempotencyKey;
        }

        if ($useClientTimestamp && $clientProcessedAt !== null) {
            $attributes['created_at'] = $clientProcessedAt;
            $attributes['updated_at'] = $clientProcessedAt;
        }

        try {
            $sale = Sale::create($attributes)->load([
                'product',
                'processedBy:id,name,user_name,email',
            ]);

            $sale = $this->ensureSaleNumber($sale);

            return [$sale, true];
        } catch (QueryException $exception) {
            if ($idempotencyKey !== null && $this->isDuplicateKeyException($exception)) {
                $existingSale = Sale::with([
                    'product',
                    'processedBy:id,name,user_name,email',
                ])->where('idempotency_key', $idempotencyKey)->first();

                if ($existingSale) {
                    $existingSale = $this->ensureSaleNumber($existingSale);

                    return [$existingSale, false];
                }
            }

            throw $exception;
        }
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();

        return in_array($sqlState, ['23000', '23505'], true);
    }

    private function resolveUnitType(?string $requestedUnitType, ?string $productUnit): ?string
    {
        $normalizedRequestUnitType = $this->normalizeUnitType($requestedUnitType);

        if ($normalizedRequestUnitType !== null) {
            return $normalizedRequestUnitType;
        }

        return $this->normalizeUnitType($productUnit);
    }

    private function normalizeUnitType(?string $unitType): ?string
    {
        if ($unitType === null) {
            return null;
        }

        $normalizedUnitType = strtolower(trim($unitType));

        if ($normalizedUnitType === '') {
            return null;
        }

        if (str_contains($normalizedUnitType, 'bag')) {
            return 'bag';
        }

        if (str_contains($normalizedUnitType, 'sack')) {
            return 'sack';
        }

        if (str_contains($normalizedUnitType, 'kilo') || str_contains($normalizedUnitType, 'kg')) {
            return 'kilo';
        }

        // Keep other unit labels (pcs, box, tray, etc.) instead of dropping them.
        return substr($normalizedUnitType, 0, 20);
    }

    private function resolveClientProcessedAt(array $validated): ?Carbon
    {
        $rawTimestampUtc = $validated['processed_at_utc'] ?? null;

        if ($rawTimestampUtc !== null && trim((string) $rawTimestampUtc) !== '') {
            return Carbon::parse((string) $rawTimestampUtc)
                ->utc()
                ->setMicrosecond(0);
        }

        $rawTimestamp = $validated['processed_at']
            ?? $validated['sold_at']
            ?? $validated['created_at']
            ?? null;

        if ($rawTimestamp === null || trim((string) $rawTimestamp) === '') {
            return null;
        }

        return Carbon::parse((string) $rawTimestamp)->setMicrosecond(0);
    }

    private function resolveRequestedSaleNumber(array $validated): ?string
    {
        $saleNumber = isset($validated['sale_number'])
            ? trim((string) $validated['sale_number'])
            : '';

        if ($saleNumber === '') {
            return null;
        }

        return substr($saleNumber, 0, 32);
    }

    private function findGroupedSaleNumber(mixed $processedByUserId, Carbon $processedAt): ?string
    {
        $query = Sale::query()
            ->whereNotNull('sale_number')
            ->where('created_at', $processedAt->copy()->toDateTimeString());

        if ($processedByUserId === null) {
            $query->whereNull('processed_by_user_id');
        } else {
            $query->where('processed_by_user_id', $processedByUserId);
        }

        return $query->orderBy('id')->value('sale_number');
    }

    private function ensureSaleNumber(Sale $sale): Sale
    {
        if (!empty($sale->sale_number)) {
            return $sale;
        }

        $timestamps = $sale->timestamps;
        $sale->timestamps = false;

        try {
            $sale->forceFill([
                'sale_number' => $this->buildSaleNumber($sale),
            ])->saveQuietly();
        } finally {
            $sale->timestamps = $timestamps;
        }

        return $sale->fresh([
            'product',
            'processedBy:id,name,user_name,email',
        ]) ?? $sale;
    }

    private function buildSaleNumber(Sale $sale): string
    {
        $rawCreatedAt = $sale->created_at;

        if ($rawCreatedAt instanceof \DateTimeInterface) {
            $datePart = $rawCreatedAt->format('Ymd');
        } elseif (is_string($rawCreatedAt) && trim($rawCreatedAt) !== '') {
            $datePart = Carbon::parse($rawCreatedAt)->format('Ymd');
        } else {
            $datePart = now()->format('Ymd');
        }

        return sprintf('SAL-%s-%06d', $datePart, (int) $sale->id);
    }
}
