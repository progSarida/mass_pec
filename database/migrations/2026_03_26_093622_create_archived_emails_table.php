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
        Schema::create('archived_emails', function (Blueprint $table) {
            $table->id();
            $table->string('protocol_number')->nullable();                                                                      // numero di protocollo della voce

            $table->string('flow_type')->nullable();                                                                            // flusso voce protocollo

            $table->foreignId('parent_id')->nullable()->constrained('archived_emails')->onUpdate('cascade');                    // identificativo account mittente

            $table->string('receiving_mail')->nullable();                                                                       // indirizzo casella di provenienza
            $table->string('uid')->nullable();                                                                                  // identificativo della mail
            $table->string('message_id')->nullable();                                                                           // identificativo unico della mail

            $table->foreignId('account_id')->nullable()->constrained('accounts')->onUpdate('cascade');                          // identificativo account mittente
            $table->json('to')->nullable();                                                                                     // array con nomi e indirizzi destinatari

            $table->foreignId('sender_id')->nullable()->constrained('recipients')->onUpdate('cascade');                         // identificativo interlocutore mittente
            $table->json('other_senders')->nullable();                                                                          // json con identificativi altri mittenti
            $table->text('from')->nullable();                                                                                   // indirizzo mittente

            $table->text('subject');                                                                                            // oggetto del messaggio
            $table->longText('body')->nullable();                                                                               // corpo del messaggio
            $table->dateTime('send_date')->nullable();                                                                          // data invio mail
            $table->dateTime('receive_date')->nullable();                                                                       // data ricezione mail

            $table->string('attachment_path')->nullable();                                                                      // percorso allegati

            $table->foreignId('download_user_id')->nullable()->constrained('users')->onUpdate('cascade');                       // id utente che ha scaricato la mail

            $table->timestamps();

            $table->unique(['message_id', 'receiving_mail']);
            $table->unique(['uid', 'receive_date']);
        });

        Schema::create('archived_receivers', function (Blueprint $table) {                                                      // tabella destinatari mail da protocollo
            $table->id();
            $table->foreignId('archived_email_id')->nullable()->constrained('archived_emails')->onUpdate('cascade');            // identificativo email archiviata
            $table->string('protocol_number')->nullable();                                                                      // numero di protocollo della voce
            $table->foreignId('recipient_id')->nullable()->constrained('recipients')->onUpdate('cascade');                      // identificativo destinatario
            $table->string('name')->nullable();                                                                                 // nome destinatario
            $table->string('address')->nullable();                                                                              // indirizzo destinatario
            $table->string('message_id')->nullable();                                                                           // identificativo univoco mail inviata
            $table->string('pec_status')->nullable();                                                                           // stato ricevute pec
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_receivers');
        Schema::dropIfExists('archived_emails');
    }
};
