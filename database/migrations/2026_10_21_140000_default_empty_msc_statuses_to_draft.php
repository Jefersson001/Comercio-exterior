<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['status', 'status_bl'] as $field) {
            DB::table('msc_shipments')->where(function ($query) use ($field): void {
                $query->whereNull($field)->orWhereRaw("TRIM({$field}) = ''");
            })->update([$field => 'Borrador']);
        }
    }

    public function down(): void
    {
        // Preserve assigned states: a draft cannot be distinguished from a backfilled blank.
    }
};
