<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->string('exp_number')->nullable();
            $table->string('dua_intr')->nullable();
            $table->string('seabridge')->nullable();
            $table->string('sencamer')->nullable();
            $table->string('comex')->nullable();
            $table->date('recognition_date')->nullable();
            $table->date('receipt_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropColumn([
                'exp_number',
                'dua_intr',
                'seabridge',
                'sencamer',
                'comex',
                'recognition_date',
                'receipt_date',
            ]);
        });
    }
};
