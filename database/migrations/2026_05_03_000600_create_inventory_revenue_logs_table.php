<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_revenue_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 40)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('expected_revenue', 12, 2)->default(0);
            $table->string('action', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_revenue_logs');
    }
};
