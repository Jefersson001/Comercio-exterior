<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->text('customs_agent_status')->nullable();
            $table->text('customs_agent_observation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropColumn(['customs_agent_status', 'customs_agent_observation']);
        });
    }
};
