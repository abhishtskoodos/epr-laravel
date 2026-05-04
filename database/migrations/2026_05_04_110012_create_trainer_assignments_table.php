<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['trainer_id', 'batch_id', 'assigned_at']);
            $table->index(['batch_id', 'unassigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_assignments');
    }
};
