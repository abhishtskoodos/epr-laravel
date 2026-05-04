<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('assessment_agency')->nullable();
            $table->string('assessor_name')->nullable();
            $table->date('assessed_on')->nullable();
            $table->decimal('theory_score', 6, 2)->nullable();
            $table->decimal('practical_score', 6, 2)->nullable();
            $table->decimal('viva_score', 6, 2)->nullable();
            $table->decimal('total_score', 6, 2)->nullable();
            $table->string('result', 16)->default('pending'); // pass, fail, pending
            $table->string('status', 32)->default('pending'); // pending, verified, rejected
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_assessments');
    }
};
