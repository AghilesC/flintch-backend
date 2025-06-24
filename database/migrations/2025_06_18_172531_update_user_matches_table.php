<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_matches', function (Blueprint $table) {
            // Vérifiez quelles colonnes existent déjà et ajoutez seulement les manquantes
            if (!Schema::hasColumn('user_matches', 'status')) {
                $table->enum('status', ['active', 'rejected', 'unmatched'])->default('active')->after('is_mutual');
            }
            if (!Schema::hasColumn('user_matches', 'matched_at')) {
                $table->timestamp('matched_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_matches', function (Blueprint $table) {
            $table->dropColumn(['status', 'matched_at']);
        });
    }
};