<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('reporter_id');
            $table->unsignedBigInteger('reported_user_id');
            $table->text('reason')->nullable();
            $table->enum('type', ['report', 'block'])->default('report');

            $table->timestamps();

            $table->foreign('reporter_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reported_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('reports');
    }
};
