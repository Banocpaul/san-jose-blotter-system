<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_settings', function (Blueprint $table) {
            $table->id();
            $table->json('targets');
            $table->unsignedTinyInteger('near_percent')->default(80);
            $table->json('non_working_dates');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::table('sla_settings')->insert([
            'id' => 1, 'targets' => json_encode(config('analytics.sla_targets')),
            'near_percent' => 80, 'non_working_dates' => json_encode(config('analytics.non_working_dates', [])),
            'revision' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_settings');
    }
};
