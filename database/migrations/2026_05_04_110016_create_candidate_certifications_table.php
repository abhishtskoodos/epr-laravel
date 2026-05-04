<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('certificate_no')->unique();
            $table->date('issued_on')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('certificate_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 32)->default('pending'); // pending, issued, revoked
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_certifications');
    }
};
