<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales') || Schema::hasColumn('sales', 'idempotency_key')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales') || !Schema::hasColumn('sales', 'idempotency_key')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
