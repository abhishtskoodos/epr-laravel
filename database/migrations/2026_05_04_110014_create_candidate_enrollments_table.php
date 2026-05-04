<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->timestamp('enrolled_at');
            $table->timestamp('dropped_at')->nullable();
            $table->string('drop_reason')->nullable();
            $table->decimal('attendance_percent', 5, 2)->nullable();
            $table->boolean('ojt_completed')->default(false);
            $table->unsignedInteger('ojt_hours')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['candidate_id', 'batch_id']);
            $table->index('dropped_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_enrollments');
    }
};
