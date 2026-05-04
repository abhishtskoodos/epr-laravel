<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheme_job_role', function (Blueprint $table) {
            $table->foreignId('scheme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_role_id')->constrained()->cascadeOnDelete();
            $table->decimal('payable_per_candidate', 10, 2)->default(0);
            $table->timestamps();

            $table->primary(['scheme_id', 'job_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_job_role');
    }
};
