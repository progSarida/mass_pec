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
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('mail_type')->after('shipment_path')->nullable();                                    // tipo mail destinatari
            $table->foreignId('region_id')->after('mail_type')->nullable()->constrained('regions');             // id regione destinatari
            $table->foreignId('province_id')->after('region_id')->nullable()->constrained('provinces');         // id provincia destinatari
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // $table->dropForeign(['region_id']);                                                                 // rimuovo i vincoli
            // $table->dropForeign(['province_id']);                                                               //

            // $table->dropColumn(['mail_type', 'region_id', 'province_id']);                                      // rimuovo le colonne
        });
    }
};
