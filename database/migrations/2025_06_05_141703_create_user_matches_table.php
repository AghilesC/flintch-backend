<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_matches', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id_1');
            $table->unsignedBigInteger('user_id_2');
            $table->boolean('matched')->default(false);

            $table->timestamps();

            $table->foreign('user_id_1')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_id_2')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('user_matches');
    }
};
