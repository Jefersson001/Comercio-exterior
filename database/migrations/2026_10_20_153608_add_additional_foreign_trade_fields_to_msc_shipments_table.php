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
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->string('shipment_year', 4)->nullable();
            $table->date('etd_vnzl')->nullable();
            $table->date('eta_vnzl')->nullable();
            $table->string('port')->nullable();
            $table->string('trasegado')->nullable();
            $table->date('in_bond_date')->nullable();
            $table->unsignedInteger('free_days')->nullable();
            $table->date('delay_date')->nullable();
            $table->date('legal_abandonment_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropColumn([
                'shipment_year',
                'etd_vnzl',
                'eta_vnzl',
                'port',
                'trasegado',
                'in_bond_date',
                'free_days',
                'delay_date',
                'legal_abandonment_date',
            ]);
        });
    }
};
