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
        Schema::create('send_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');                                   // id account mittente
            $table->json('recipients')->nullable();                                                     // destinatari
            $table->text('subject');                                                                    // oggetto del messaggio
            $table->text('body');                                                                       // corpo del messaggio
            $table->string('attachment_path')->nullable();                                              // percorso allegati
            $table->date('create_date');                                                                // data creazione mail
            $table->foreignId('create_user_id')->constrained('users')->onUpdate('cascade');             // id utente che ha creato la mail
            $table->date('send_date')->nullable();                                                      // data invio mail
            $table->foreignId('send_user_id')->nullable()->constrained('users')->onUpdate('cascade');   // id utente che ha inviato la mail
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('send_emails');
    }
};
