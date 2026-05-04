<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheme_eligibility_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key', 32); // age_min, age_max, gender, category, education_min, state, income_max, custom
            $table->string('operator', 16); // eq, neq, gte, lte, in, not_in, between
            $table->json('value_json');
            $table->boolean('is_blocking')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['scheme_id', 'rule_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_eligibility_rules');
    }
};
