<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales') || Schema::hasColumn('sales', 'processed_by_user_id')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('processed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales') || !Schema::hasColumn('sales', 'processed_by_user_id')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processed_by_user_id');
        });
    }
};
