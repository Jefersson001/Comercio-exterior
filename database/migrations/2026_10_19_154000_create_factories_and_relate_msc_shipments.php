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
        Schema::create('factories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->foreignId('factory_id')->nullable()->constrained()->restrictOnDelete();
        });

        $factoryIds = [];

        DB::table('msc_shipments')->select(['id', 'factory'])->orderBy('id')->chunkById(500, function ($shipments) use (&$factoryIds): void {
            foreach ($shipments as $shipment) {
                $name = is_string($shipment->factory) ? Str::squish($shipment->factory) : '';

                if ($name === '' || Str::length($name) > 255) {
                    continue;
                }

                $key = Str::lower(Str::ascii($name));
                if (! isset($factoryIds[$key])) {
                    $factoryIds[$key] = DB::table('factories')->insertGetId([
                        'name' => $name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('msc_shipments')->where('id', $shipment->id)->update(['factory_id' => $factoryIds[$key]]);
            }
        });

        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropColumn('factory');
        });
    }

    public function down(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->string('factory')->nullable();
        });

        DB::table('msc_shipments')->select(['id', 'factory_id'])->orderBy('id')->chunkById(500, function ($shipments): void {
            $factories = DB::table('factories')->whereIn('id', $shipments->pluck('factory_id')->filter())->pluck('name', 'id');

            foreach ($shipments as $shipment) {
                $factoryId = $shipment->factory_id;
                DB::table('msc_shipments')->where('id', $shipment->id)->update([
                    'factory' => $factoryId === null ? null : ($factories[$factoryId] ?? null),
                ]);
            }
        });

        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('factory_id');
        });

        Schema::dropIfExists('factories');
    }
};
