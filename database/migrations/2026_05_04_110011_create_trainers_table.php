<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('pan', 10)->nullable()->unique();
            $table->string('aadhaar_token', 64)->nullable()->index();
            $table->string('aadhaar_last4', 4)->nullable();
            $table->string('qualification')->nullable();
            $table->string('tot_certificate_no')->nullable();
            $table->decimal('experience_years', 4, 1)->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainers');
    }
};
