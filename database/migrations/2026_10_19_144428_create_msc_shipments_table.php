<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('msc_shipments', function (Blueprint $table) {
            $table->id();
            $table->date('etd')->index();
            $table->date('eta')->index();
            $table->string('bill_of_lading')->unique();
            $table->string('factory');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('container_qty');
            $table->unsignedInteger('free_time');
            $table->string('ship_agent')->nullable();
            $table->string('released')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msc_shipments');
    }
};
