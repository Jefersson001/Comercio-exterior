<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('units_per_container')->nullable();
        });

        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->unsignedInteger('units_qty')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropColumn('units_qty');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('units_per_container');
        });
    }
};
