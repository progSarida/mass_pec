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
        Schema::table('registries', function (Blueprint $table) {
            // 1. Rimuovo il vecchio vincolo unique sulla singola colonna
            $table->dropUnique(['message_id']);

            // 2. Aggiungo il nuovo vincolo composto
            $table->unique(['message_id', 'registry_origin_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            // 1. Rimuovo il vincolo composto creato nell'up
            // Passando l'array, Laravel ricostruisce il nome automatico per eliminarlo
            $table->dropUnique(['message_id', 'registry_origin_type']);

            // 2. Ripristino il vincolo originale sulla singola colonna
            $table->unique('message_id');
        });
    }
};
