<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_roles', function (Blueprint $table) {
            $table->id();
            $table->string('qp_code', 32)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('nsqf_level')->nullable();
            $table->string('sector')->nullable();
            $table->timestamps();

            $table->index('sector');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_roles');
    }
};
