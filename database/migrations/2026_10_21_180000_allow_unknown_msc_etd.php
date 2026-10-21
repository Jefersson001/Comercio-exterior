<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->date('etd')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('msc_shipments')->whereNull('etd')->exists()) {
            throw new RuntimeException('No se puede exigir ETD mientras existan embarques sin fecha conocida.');
        }
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->date('etd')->nullable(false)->change();
        });
    }
};
