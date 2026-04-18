<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales') || !Schema::hasColumn('sales', 'unit_type')) {
            return;
        }

        if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'unit')) {
            return;
        }

        $rows = DB::table('sales')
            ->leftJoin('products', 'sales.product_id', '=', 'products.id')
            ->whereNull('sales.unit_type')
            ->select(['sales.id as sale_id', 'products.unit as product_unit'])
            ->orderBy('sales.id')
            ->get();

        foreach ($rows as $row) {
            $normalizedUnitType = $this->normalizeUnitType($row->product_unit);

            if ($normalizedUnitType === null) {
                continue;
            }

            DB::table('sales')
                ->where('id', $row->sale_id)
                ->update(['unit_type' => $normalizedUnitType]);
        }
    }

    public function down(): void
    {
        // No-op: keep backfilled values.
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

        return substr($normalizedUnitType, 0, 20);
    }
};
