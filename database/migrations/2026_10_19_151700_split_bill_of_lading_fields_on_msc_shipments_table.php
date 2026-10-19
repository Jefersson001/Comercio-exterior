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
            $table->string('bl_master')->nullable()->after('eta');
            $table->string('bl_house')->default('')->after('bl_master');
        });

        DB::table('msc_shipments')->select(['id', 'bill_of_lading'])->orderBy('id')->chunkById(500, function ($shipments): void {
            foreach ($shipments as $shipment) {
                $lines = preg_split('/\r\n|\r|\n/', $shipment->bill_of_lading) ?: [];
                $lines = array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));

                DB::table('msc_shipments')->where('id', $shipment->id)->update([
                    'bl_master' => $lines[0] ?? '',
                    'bl_house' => isset($lines[1]) ? implode("\n", array_slice($lines, 1)) : '',
                ]);
            }
        });

        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropUnique(['bill_of_lading']);
            $table->dropColumn('bill_of_lading');
            $table->unique(['bl_master', 'bl_house']);
        });
    }

    public function down(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropUnique(['bl_master', 'bl_house']);
            $table->string('bill_of_lading')->nullable();
        });

        DB::table('msc_shipments')->select(['id', 'bl_master', 'bl_house'])->orderBy('id')->chunkById(500, function ($shipments): void {
            foreach ($shipments as $shipment) {
                DB::table('msc_shipments')->where('id', $shipment->id)->update([
                    'bill_of_lading' => $shipment->bl_house === '' ? $shipment->bl_master : $shipment->bl_master."\n".$shipment->bl_house,
                ]);
            }
        });

        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->unique('bill_of_lading');
            $table->dropColumn(['bl_master', 'bl_house']);
        });
    }
};
