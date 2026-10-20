<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->date('original_docs_receipt_date')->nullable();
            $table->date('aa_instructions_sent_date')->nullable();
            $table->date('bl_release_date')->nullable();
            $table->date('sencamer_request_date')->nullable();
            $table->date('sencamer_receipt_date')->nullable();
            $table->date('comex_request_date')->nullable();
            $table->date('comex_receipt_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('msc_shipments', function (Blueprint $table): void {
            $table->dropColumn([
                'original_docs_receipt_date',
                'aa_instructions_sent_date',
                'bl_release_date',
                'sencamer_request_date',
                'sencamer_receipt_date',
                'comex_request_date',
                'comex_receipt_date',
            ]);
        });
    }
};
