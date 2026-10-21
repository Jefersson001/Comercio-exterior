<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_changes', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 30);
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('event', 20);
            $table->string('source', 20);
            $table->json('changes');
            $table->timestamp('created_at');
            $table->index(['subject_type', 'subject_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_changes');
    }
};
