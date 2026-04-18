<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales') || Schema::hasColumn('sales', 'unit_type')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->string('unit_type', 20)->nullable()->after('processed_by_user_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales') || !Schema::hasColumn('sales', 'unit_type')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('unit_type');
        });
    }
};
