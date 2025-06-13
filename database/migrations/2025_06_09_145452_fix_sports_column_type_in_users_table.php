<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sports'); // ⚠️ supprime l'ancienne colonne
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('sports')->nullable(); // recrée en type JSON
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sports');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('sports')->nullable(); // rollback vers texte
        });
    }
};
