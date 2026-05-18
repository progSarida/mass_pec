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
        Schema::create('manual_inserts', function (Blueprint $table) {
            $table->id();
            $table->string('flow_type')->nullable();                                                                                // flusso
            $table->json('receivers')->nullable();                                                                                  // json con identificativi destinatari
            $table->json('senders')->nullable();                                                                                    // json con identificativi mittenti
            $table->json('interested_parties')->nullable();                                                                         // json con identificativi parti interessate
            // $table->json('addresses')->nullable();                                                                                  // json con indirizzi
            $table->text('subject')->nullable();                                                                                    // oggetto del messaggio
            $table->text('body')->nullable();                                                                                       // corpo del messaggio
            $table->date('receive_date')->nullable();                                                                               // data ricezione
            $table->date('send_date')->nullable();                                                                                  // data invio
            $table->date('internal_date')->nullable();                                                                              // data comunicazione interna
            $table->foreignId('create_user_id')->nullable()->constrained('users')->onUpdate('cascade');                             // id utente che ha creato l'elemento
            $table->string('attachment_path')->nullable();                                                                          // percorso allegati
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_inserts');
    }
};
