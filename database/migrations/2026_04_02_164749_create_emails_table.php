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
        Schema::create('emails', function (Blueprint $table) {                                                                          // tabella email di account non pec
            $table->id();

            // campi comuni alle mail ricevute e inviate
            $table->string('flow_type')->nullable();                                                                                    // flusso voce protocollo
            $table->integer('flow_index')->nullable();                                                                                  // indice di ordinamento flusso voce
            $table->foreignId('scope_type_id')->nullable()->constrained('scope_types')->onUpdate('cascade');                            // id settore interno mail
            $table->foreignId('parent_id')->nullable()->constrained('emails')->onUpdate('cascade');                                     // id voce protocollo correlata
            $table->string('uid')->nullable();                                                                                          // identificativo della mail
            $table->string('message_id')->nullable()->unique();                                                                         // identificativo unico della mail
            $table->text('subject')->nullable();                                                                                        // oggetto del messaggio
            $table->text('body')->nullable();                                                                                           // corpo del messaggio
            $table->string('attachment_path')->nullable();                                                                              // percorso allegati
            $table->string('manage_email_type')->nullable();                                                                            // json con id altri mittenti
            $table->date('manage_email_date')->nullable();                                                                              // data gestione

            // campi mail ricevute
            $table->string('receiving_mail')->nullable();                                                                               // indirizzo su cui è arrivata l'email
            $table->text('from')->nullable();                                                                                           // mittente
            $table->foreignId('sender_id')->nullable()->constrained('recipients')->onUpdate('cascade');                                 // id voce protocollo correlata
            $table->json('other_senders')->nullable();                                                                                  // json con id altri mittenti
            $table->datetime('receive_date')->nullable();                                                                               // data ricezione mail
            $table->foreignId('download_user_id')->nullable()->constrained('users')->onUpdate('cascade');                               // id utente che ha scaricato la mail

            // campi mail inviate
            $table->foreignId('account_id')->nullable()->constrained('accounts')->onUpdate('cascade');                                  // id account mittente
            $table->foreignId('signature_id')->nullable()->constrained('signatures')->onUpdate('cascade');                              // id firma messaggio
            $table->string('mail_type')->nullable();                                                                                    // tipo mail
            $table->foreignId('office_type_id')->nullable()->constrained('office_types')->onUpdate('cascade');                          // id tipo ufficio
            $table->json('recipients')->nullable();                                                                                     // destinatari
            $table->foreignId('create_user_id')->nullable()->constrained('users')->onUpdate('cascade');                                 // id utente che ha registrato la mail
            $table->foreignId('send_user_id')->nullable()->constrained('users')->onUpdate('cascade');                                   // id utente che ha effettuato l'invio
            $table->datetime('send_date')->nullable();                                                                                  // data di invio

            $table->timestamps();

            $table->unique(['message_id', 'receiving_mail']);                                                                           // regola per unicità email
        });

        // Schema::create('email_receivers', function (Blueprint $table) {                                                                 // tabella destinatari email non pec
        //     $table->id();
        //     $table->foreignId('email_id')->nullable()->constrained('emails')->onUpdate('cascade');                                      // identificativo email archiviata
        //     $table->foreignId('recipient_id')->nullable()->constrained('recipients')->onUpdate('cascade');                              // identificativo destinatario
        //     $table->string('name')->nullable();                                                                                         // nome destinatario
        //     $table->string('address')->nullable();                                                                                      // indirizzo destinatario
        //     $table->string('message_id')->nullable();                                                                                   // identificativo univoco mail inviata
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_receivers');
        Schema::dropIfExists('emails');
    }
};
