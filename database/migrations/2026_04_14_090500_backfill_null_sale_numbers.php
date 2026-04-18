<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('sales')
            ->select(['id', 'created_at'])
            ->whereNull('sale_number')
            ->orderBy('id')
            ->get();

        foreach ($rows as $sale) {
            $datePart = $sale->created_at
                ? date('Ymd', strtotime((string) $sale->created_at))
                : date('Ymd');

            $saleNumber = sprintf('SAL-%s-%06d', $datePart, (int) $sale->id);

            DB::table('sales')
                ->where('id', $sale->id)
                ->update(['sale_number' => $saleNumber]);
        }
    }

    public function down(): void
    {
        // No-op: preserve generated sale numbers.
    }
};
