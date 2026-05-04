<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employer_name');
            $table->string('employer_pan', 10)->nullable();
            $table->string('designation');
            $table->decimal('monthly_ctc', 10, 2);
            $table->string('placement_type', 32); // wage, self_employed, apprentice
            $table->date('placed_on');
            $table->date('verified_on')->nullable();
            $table->foreignId('proof_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 32)->default('pending'); // pending, verified, rejected
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_placements');
    }
};
