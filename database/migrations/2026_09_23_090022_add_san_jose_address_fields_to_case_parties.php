<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'case_complainants',
            function (Blueprint $table) {
                $table
                    ->boolean('is_san_jose_resident')
                    ->default(true)
                    ->after('resident_id');

                $table
                    ->string('house_number', 50)
                    ->nullable()
                    ->after('is_san_jose_resident');

                $table
                    ->string('sitio', 20)
                    ->nullable()
                    ->after('house_number');
            }
        );

        Schema::table(
            'case_respondents',
            function (Blueprint $table) {
                $table
                    ->boolean('is_san_jose_resident')
                    ->default(true)
                    ->after('resident_id');

                $table
                    ->string('house_number', 50)
                    ->nullable()
                    ->after('is_san_jose_resident');

                $table
                    ->string('sitio', 20)
                    ->nullable()
                    ->after('house_number');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'case_complainants',
            function (Blueprint $table) {
                $table->dropColumn([
                    'is_san_jose_resident',
                    'house_number',
                    'sitio',
                ]);
            }
        );

        Schema::table(
            'case_respondents',
            function (Blueprint $table) {
                $table->dropColumn([
                    'is_san_jose_resident',
                    'house_number',
                    'sitio',
                ]);
            }
        );
    }
};