<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            // Ajoute les colonnes seulement si elles n'existent pas
            if (!Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable();
            }

            if (!Schema::hasColumn('users', 'location')) {
                $table->string('location')->nullable();
            }

            if (!Schema::hasColumn('users', 'interests')) {
                $table->json('interests')->nullable();
            }

            if (!Schema::hasColumn('users', 'gender')) {
                $table->enum('gender', ['male', 'female', 'other'])->nullable();
            }

            if (!Schema::hasColumn('users', 'looking_for')) {
                $table->enum('looking_for', ['male', 'female', 'both'])->nullable();
            }

            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }

            if (!Schema::hasColumn('users', 'last_active')) {
                $table->timestamp('last_active')->nullable();
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $table->index('is_active');
            }
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'bio',
                'location',
                'interests',
                'gender',
                'looking_for',
                'is_active',
                'last_active',
            ]);
        });
    }
};
