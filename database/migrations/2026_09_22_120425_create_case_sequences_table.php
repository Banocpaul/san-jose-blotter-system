<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_sequences', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('current_number')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_sequences');
    }
};