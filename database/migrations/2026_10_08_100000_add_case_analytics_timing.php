<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blotter_cases', function (Blueprint $table) {
            // Existing cases stay unknown until an actual event/date is recorded.
            $table->timestamp('stage_entered_at')->nullable();
            $table->timestamp('tracked_opened_at')->nullable();
            $table->timestamp('sla_started_at')->nullable();
            $table->unsignedSmallInteger('sla_extension_days')->default(0);
            $table->text('sla_extension_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blotter_cases', function (Blueprint $table) {
            $table->dropColumn(['stage_entered_at', 'tracked_opened_at', 'sla_started_at',
                'sla_extension_days', 'sla_extension_reason']);
        });
    }
};
