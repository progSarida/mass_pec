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
            $table->date('send_date')->after('province_id')->nullable();                                                        // data di invio della spedizione
            $table->foreignId('send_user_id')->after('send_date')->nullable()->constrained('users')->onUpdate('cascade');       // id utente che ha inviato la spedizione
        });

        Schema::table('registries', function (Blueprint $table) {
            $table->date('receive_date')->nullable()->change();                                                                 // data ricezione mail
            $table->date('download_date')->nullable()->change();                                                                // data ricezione mail
            $table->date('send_date')->after('receive_date')->nullable();                                                       // data di invio
            $table->foreignId('send_user_id')->after('send_date')->nullable()->constrained('users')->onUpdate('cascade');       // id utente che ha effettuato l'invio
            $table->foreignId('shipment_id')->after('send_user_id')->nullable()->constrained('shipments')->onUpdate('cascade'); // id utente che ha effettuato l'invio
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // $table->dropForeign(['send_user_id']);                                                                              // rimuovo il vincolo
            // $table->dropColumn(['send_date', 'send_user_id']);                                                                  // rimuovo le colonne
        });

        Schema::table('registries', function (Blueprint $table) {
            // $table->dropForeign(['send_user_id']);                                                                              // rimuovo i vincoli
            // $table->dropForeign(['shipment_id']);                                                                               //
            // $table->dropColumn(['send_date', 'send_user_id','shipment_id']);                                                    // rimuovo le colonne
        });
    }
};
