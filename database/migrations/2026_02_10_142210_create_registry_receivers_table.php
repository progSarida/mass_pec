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
        Schema::create('registry_receivers', function (Blueprint $table) {                                                      // tabella destinatari mail da protocollo
            $table->id();
            $table->foreignId('registry_id')->nullable()->constrained('registries')                                             // id voce protocollo
                    ->onUpdate('cascade')->onDelete('cascade');                                                                 //
            $table->string('protocol_number');                                                                                  // numero di protocollo della voce
            $table->string('address')->nullable();                                                                              // indirizzo destinatario
            $table->string('message_id')->nullable();                                                                           // identificativo univoco mail inviata
            $table->string('pec_status')->nullable();                                                                           // stato ricevute pec
            $table->timestamps();
        });

        Schema::table('registries', function (Blueprint $table) {
            $table->dropColumn(['recipients']);                                                                                 // rimuovo la colonna dei destinatari
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registry_receivers');
    }
};
