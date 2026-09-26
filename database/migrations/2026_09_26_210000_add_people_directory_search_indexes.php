<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->index(
                ['is_active', 'deleted_at', 'last_name', 'first_name'],
                'idx_people_active_name'
            );

            $table->index(
                'contact_number',
                'idx_people_contact_number'
            );
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropIndex('idx_people_active_name');
            $table->dropIndex('idx_people_contact_number');
        });
    }
};
