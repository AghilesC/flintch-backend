<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('profile_photo')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('gender')->nullable();

            // Spécifique au sport
            $table->json('sports')->nullable(); // liste de sports
            $table->string('fitness_level')->nullable(); // débutant / intermédiaire / pro
            $table->text('goals')->nullable();
            $table->string('availability')->nullable();
            $table->string('location')->nullable(); // ville ou quartier
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();

            // Premium
            $table->boolean('is_premium')->default(false);

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};
