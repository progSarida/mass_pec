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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->boolean('download');                                                // flag download email
            $table->string('management_type', 50);
            $table->string('mail_type', 50);
            $table->string('address', 100);                                             // indirizzo email
            $table->string('username', 100);                                            // username account
            $table->string('password', 250);                                            // password account
            $table->string('public_name', 100);                                         // nome account
            $table->string('connection_safety_type', 30);                               // tipo connessione
            $table->string('in_mail_server', 100);                                      // server ingresso
            $table->string('in_mail_protocol_type', 10);                                // protocollo ingresso
            $table->string('in_mail_port', 10);                                         // porta ingresso
            $table->boolean('delete');                                                  // flag cancellazione dal server
            $table->integer('delete_after_days')->nullable();                           // giorni dopo i quali cancellare una mail dalla casella
            $table->string('out_mail_server', 100);                                     // server uscita
            $table->string('out_mail_protocol_type', 10);                               // protocollo uscita
            $table->string('out_mail_port', 10);                                        // porta uscita
            $table->boolean('out_authentication');                                      // richiesta autenticazione
            $table->string('out_username', 100);                                        // username uscita
            $table->string('out_password', 250);                                        // password uscita
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('accounts');
        Schema::enableForeignKeyConstraints();
    }
};
