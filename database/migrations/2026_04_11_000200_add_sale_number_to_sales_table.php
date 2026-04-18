<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales')) {
            return;
        }

        if (!Schema::hasColumn('sales', 'sale_number')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('sale_number', 32)->nullable()->unique();
            });
        }

        $sales = DB::table('sales')
            ->select(['id', 'created_at', 'sale_number'])
            ->whereNull('sale_number')
            ->orderBy('id')
            ->get();

        foreach ($sales as $sale) {
            $datePart = $sale->created_at ? date('Ymd', strtotime((string) $sale->created_at)) : date('Ymd');
            $saleNumber = sprintf('SAL-%s-%06d', $datePart, (int) $sale->id);

            DB::table('sales')
                ->where('id', $sale->id)
                ->update(['sale_number' => $saleNumber]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales') || !Schema::hasColumn('sales', 'sale_number')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['sale_number']);
            $table->dropColumn('sale_number');
        });
    }
};
