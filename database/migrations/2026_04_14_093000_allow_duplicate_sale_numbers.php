<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales') || !Schema::hasColumn('sales', 'sale_number')) {
            return;
        }

        try {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropUnique('sales_sale_number_unique');
            });
        } catch (\Throwable $exception) {
            // Ignore if unique index is already dropped.
        }

        try {
            Schema::table('sales', function (Blueprint $table) {
                $table->index('sale_number', 'sales_sale_number_index');
            });
        } catch (\Throwable $exception) {
            // Ignore if index already exists.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales') || !Schema::hasColumn('sales', 'sale_number')) {
            return;
        }

        try {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropIndex('sales_sale_number_index');
            });
        } catch (\Throwable $exception) {
            // Ignore if index does not exist.
        }

        // Intentionally not restoring a unique constraint because duplicates may already exist.
    }
};
