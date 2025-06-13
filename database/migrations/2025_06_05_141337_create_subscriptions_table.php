<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->enum('type', ['monthly', 'yearly']);
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();


            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('subscriptions');
    }
};
