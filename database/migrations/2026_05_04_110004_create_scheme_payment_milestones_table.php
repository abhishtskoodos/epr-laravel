<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheme_payment_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_id')->constrained()->cascadeOnDelete();
            $table->string('key', 32); // enrollment, mid_training, assessment_pass, certification, placement_3m, placement_6m, custom
            $table->string('label');
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('requires_status', 32)->nullable(); // candidate status that must be reached
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['scheme_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_payment_milestones');
    }
};
