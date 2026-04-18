<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('company_name')->default('Admin Dashboard');
                $table->string('support_email')->nullable();
                $table->string('timezone')->default('UTC');
                $table->string('currency')->default('USD');
                $table->unsignedInteger('low_stock_threshold')->default(10);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
