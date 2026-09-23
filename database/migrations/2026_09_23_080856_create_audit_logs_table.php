<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string(
                'action',
                100
            );

            $table->string(
                'module',
                100
            )->nullable();

            $table->string(
                'description',
                1000
            )->nullable();

            $table->string(
                'auditable_type',
                255
            )->nullable();

            $table->unsignedBigInteger(
                'auditable_id'
            )->nullable();

            $table->json(
                'old_values'
            )->nullable();

            $table->json(
                'new_values'
            )->nullable();

            $table->string(
                'ip_address',
                45
            )->nullable();

            $table->text(
                'user_agent'
            )->nullable();

            $table->timestamps();

            $table->index([
                'auditable_type',
                'auditable_id',
            ]);

            $table->index(
                'action'
            );

            $table->index(
                'module'
            );

            $table->index(
                'created_at'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'audit_logs'
        );
    }
};