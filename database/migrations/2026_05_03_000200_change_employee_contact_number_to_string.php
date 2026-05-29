<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('contact_number', 20)->change();
            $table->dropUnique('employees_address_unique');
            $table->string('address')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->integer('contact_number')->change();
            $table->string('address')->nullable(false)->change();
            $table->unique('address');
        });
    }
};
