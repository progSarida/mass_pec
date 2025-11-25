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
        Schema::create('receivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onUpdate('cascade')->onDelete('cascade');       // id spedizione
            $table->string('ref')->nullable()->comment('Riferimento per scaricare le ricevute di risposta. ' .          //
                                            'Esempio [1_45_5492-1] ' .                                                  //
                                            '1 - Id della table shipments ' .                                           // riferimento per scaricare le ricevute di risposta
                                            '45 - Id della table receivers ' .                                          //
                                            '5492 - Id della table recipients ' .                                       //
                                            '- Dettaglio aggiuntivo. (es. distingue la mail utilizzata) ');             //
            $table->string('address');                                                                                  // indirizzo ricevente
            $table->string('mail_type');                                                                                // tipo mail
            $table->string('send_date')->nullable();                                                                    // data invio
            $table->string('send_receipt')->nullable();                                                                 // ricevuta di risposta
            $table->string('delivery_receipt')->nullable();                                                             // ricevuta di consegna
            $table->string('anomaly_receipt')->nullable();                                                              // ricevuta di anomalia
            $table->foreignId('recipient_id')->constrained('recipients')->onUpdate('cascade');                          // id destinatario
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('receivers');
        Schema::enableForeignKeyConstraints();
    }
};
