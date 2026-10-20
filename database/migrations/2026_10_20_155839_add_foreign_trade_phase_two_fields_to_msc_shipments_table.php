<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->date('registration_date')->nullable();
            $table->date('phase_two_recognition_date')->nullable();
            $table->date('validation_date')->nullable();
            $table->date('plant_receipt_date')->nullable();
            $table->string('location')->nullable();
            $table->date('return_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropColumn([
                'registration_date',
                'phase_two_recognition_date',
                'validation_date',
                'plant_receipt_date',
                'location',
                'return_date',
            ]);
        });
    }
};
