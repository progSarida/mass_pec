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
            $table->text('anomaly_description')->nullable()->after('pec_status');                                   // commento gestione anomalia invio pec
            $table->boolean('anomaly_managed')->default(false)->after('anomaly_description');                       // flag gestione anomalia invio pec
            $table->text('anomaly_note')->nullable()->after('anomaly_managed');                                     // commento gestione anomalia invio pec
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registry_receivers', function (Blueprint $table) {
            $table->dropColumn(['anomaly_description', 'anomaly_managed', 'anomaly_note']);
        });
    }
};
