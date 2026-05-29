<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('branches') && !Schema::hasColumn('branches', 'manager_user_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->foreignId('manager_user_id')
                    ->nullable()
                    ->after('location')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'manager_user_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropConstrainedForeignId('manager_user_id');
            });
        }
    }
};
