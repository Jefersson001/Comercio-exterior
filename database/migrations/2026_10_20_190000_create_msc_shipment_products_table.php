<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msc_shipment_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('msc_shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('container_qty');
            $table->unsignedInteger('units_qty')->nullable();
            $table->timestamps();
            $table->unique(['msc_shipment_id', 'product_id']);
        });

        DB::table('msc_shipments')->orderBy('id')->chunkById(500, function ($shipments): void {
            $rows = [];
            foreach ($shipments as $shipment) {
                $rows[] = [
                    'msc_shipment_id' => $shipment->id, 'product_id' => $shipment->product_id,
                    'container_qty' => $shipment->container_qty, 'units_qty' => $shipment->units_qty,
                    'created_at' => $shipment->created_at, 'updated_at' => $shipment->updated_at,
                ];
            }
            if ($rows !== []) {
                DB::table('msc_shipment_products')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msc_shipment_products');
    }
};
