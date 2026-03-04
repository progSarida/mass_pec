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
        Schema::table('registry_receivers', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable()->after('protocol_number')->constrained('recipients');        // id mittente
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registry_receivers', function (Blueprint $table) {
            $table->dropForeign(['recipient_id']);                                                               // rimuovo i vincoli
            $table->dropColumn(['recipient_id']);                                                                 // rimuovo le colonne
        });
    }
};
