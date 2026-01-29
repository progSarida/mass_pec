<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            // Rimuovo il vecchio indice unico
            $table->dropUnique(['uid', 'receive_date']);

            // Aggiungo il nuovo indice unico
            $table->unique(['uid', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            // Per il rollback: tolgo il nuovo e rimetto il vecchio
            $table->dropUnique(['uid', 'created_at']);
            $table->unique(['uid', 'receive_date']);
        });
    }
};
