<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter les colonnes manquantes à users
        Schema::table('users', function (Blueprint $table) {
            $columns = Schema::getColumnListing('users');
            
            if (!in_array('age', $columns)) {
                $table->integer('age')->nullable()->after('email');
            }
            if (!in_array('bio', $columns)) {
                $table->text('bio')->nullable();
            }
            if (!in_array('location', $columns)) {
                $table->string('location')->nullable();
            }
            if (!in_array('interests', $columns)) {
                $table->json('interests')->nullable();
            }
            if (!in_array('gender', $columns)) {
                $table->enum('gender', ['male', 'female', 'other'])->nullable();
            }
            if (!in_array('looking_for', $columns)) {
                $table->enum('looking_for', ['male', 'female', 'both'])->nullable();
            }
            if (!in_array('is_active', $columns)) {
                $table->boolean('is_active')->default(true);
            }
            if (!in_array('last_active', $columns)) {
                $table->timestamp('last_active')->nullable();
            }
        });
        
        // Créer user_matches si elle n'existe pas
        if (!Schema::hasTable('user_matches')) {
            Schema::create('user_matches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('matched_user_id');
                $table->boolean('is_mutual')->default(false);
                $table->enum('status', ['active', 'rejected', 'unmatched'])->default('active');
                $table->timestamp('matched_at')->nullable();
                $table->timestamps();
                
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('matched_user_id')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['user_id', 'matched_user_id']);
            });
        }
    }

    public function down(): void
    {
        // Pas de down car c'est une correction
    }
};