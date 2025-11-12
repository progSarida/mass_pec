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
        Schema::create('scope_types', function (Blueprint $table) {                                             // tabella tipi istat
            $table->id();
            $table->string('name');                                                                             // tipo istat
            $table->string('description');                                                                             // tipo istat
            $table->integer('position');                                                                        // posizione il selezione
            $table->timestamps();
        });

        Schema::create('registries', function (Blueprint $table) {
            $table->id();
            $table->string('protocol_number')->unique();                                                        // numero di protocollo
            $table->foreignId('scope_type_id')->nullable()->constrained('scope_types')->onUpdate('cascade');    // id ambito mail
            $table->string('uid')->nullable();                                                                  // identificativo della mail
            $table->string('message_id')->nullable()->unique();                                                 // identificativo unico della mail
            $table->text('from');                                                                               // mittente
            $table->text('subject');                                                                            // oggetto del messaggio
            $table->text('body')->nullable();                                                                   // corpo del messaggio
            $table->date('receive_date');                                                                       // data ricezione mail
            $table->string('attachment_path')->nullable();                                                      // percorso allegati
            $table->date('download_date');                                                                       // data ricezione mail
            $table->foreignId('download_user_id')->nullable()->constrained('users')->onUpdate('cascade');       // id utente che ha scaricato la mail
            $table->foreignId('register_user_id')->nullable()->constrained('users')->onUpdate('cascade');       // id utente che ha registrato la mail
            $table->timestamps();

            $table->unique(['uid', 'receive_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('registries');
        Schema::dropIfExists('scope_types');
        Schema::enableForeignKeyConstraints();
    }
};
