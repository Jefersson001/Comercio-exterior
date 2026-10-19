<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessels', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->foreignId('vessel_id')->nullable()->constrained()->restrictOnDelete();
        });

        $vesselIds = [];

        DB::table('msc_shipments')->select(['id', 'ship_agent'])->orderBy('id')->chunkById(500, function ($shipments) use (&$vesselIds): void {
            foreach ($shipments as $shipment) {
                $name = is_string($shipment->ship_agent) ? Str::squish($shipment->ship_agent) : '';

                if ($name === '' || Str::length($name) > 255) {
                    continue;
                }

                $key = Str::lower(Str::ascii($name));
                if (! isset($vesselIds[$key])) {
                    $vesselIds[$key] = DB::table('vessels')->insertGetId([
                        'name' => $name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('msc_shipments')->where('id', $shipment->id)->update(['vessel_id' => $vesselIds[$key]]);
            }
        });

        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropColumn('ship_agent');
        });
    }

    public function down(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->string('ship_agent')->nullable();
        });

        DB::table('msc_shipments')->select(['id', 'vessel_id'])->orderBy('id')->chunkById(500, function ($shipments): void {
            $vessels = DB::table('vessels')->whereIn('id', $shipments->pluck('vessel_id')->filter())->pluck('name', 'id');

            foreach ($shipments as $shipment) {
                $vesselId = $shipment->vessel_id;
                DB::table('msc_shipments')->where('id', $shipment->id)->update([
                    'ship_agent' => $vesselId === null ? null : ($vessels[$vesselId] ?? null),
                ]);
            }
        });

        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vessel_id');
        });

        Schema::dropIfExists('vessels');
    }
};
