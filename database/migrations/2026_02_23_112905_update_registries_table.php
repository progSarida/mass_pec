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
            $table->foreignId('parent_id')->nullable()->after('registry_origin_type')->constrained('registries');        // id voce protocollo correlata
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registries', function (Blueprint $table) {
        // 1. Rimuovo il vincolo della chiave esterna
        $table->dropForeign(['parent_id']);

        // 2. Elimino la colonna
        $table->dropColumn('parent_id');
    });
    }
};
