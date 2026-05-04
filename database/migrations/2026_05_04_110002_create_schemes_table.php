<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('funding_agency')->nullable(); // NSDC, MSDE, State, etc.
            $table->string('scheme_type', 32); // short_term | long_term | apprenticeship | rpl | placement_linked
            $table->text('description')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('min_attendance_percent', 5, 2)->default(70);
            $table->boolean('requires_assessment')->default(true);
            $table->boolean('requires_placement')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'scheme_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schemes');
    }
};
