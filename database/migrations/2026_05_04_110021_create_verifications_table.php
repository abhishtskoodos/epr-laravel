<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->morphs('verifiable');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('verifier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['verifiable_type', 'verifiable_id', 'created_at'], 'verifications_timeline_idx');
            $table->index(['verifier_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};
