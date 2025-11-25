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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('description');                                                                  // descrizione spedizione
            $table->foreignId('sender_id')->nullable()->constrained('senders')->onUpdate('cascade');        // id mittente
            $table->string('mail_object');                                                                  // oggetto della mail
            $table->string('mail_body');                                                                    // contenuto dell amail
            $table->string('attachment');                                                                   // nome  allegato
            $table->string('send_type')->default('IAB');                                                    // tipo invio,  mai modificato
            $table->date('insert_date');                                                                    // data inserimento spedizione
            $table->string('shipment_path')->nullable();                                                    // percorso cartella file spedizione
            $table->integer('total_no_mails')->default(0);                                                  // numero mail spedizione
            $table->integer('no_mails_sended')->default(0);                                                 // numero mail  inviate
            $table->integer('no_mails_to_send')->default(0);                                                // numero mail da inviare
            $table->integer('no_send_receipt')->default(0);                                                 // numero ricevute di invio
            $table->integer('no_missed_send_receipt')->default(0);                                          // numero ricevute di invio mancanti
            $table->integer('no_delivery_receipt')->default(0);                                             // numero ricevute di consegna
            $table->integer('no_missed_delivery_receipt')->default(0);                                      // numero ricevute di consegna  mancanti
            $table->integer('no_anomaly_receipt')->default(0);                                              // numero messaggi di anomalia
            $table->date('extraction_date')->nullable();                                                    // data estrazione
            $table->string('extraction_zip_file')->nullable();                                              // nome file estrazione
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('shipments');
        Schema::enableForeignKeyConstraints();
    }
};
