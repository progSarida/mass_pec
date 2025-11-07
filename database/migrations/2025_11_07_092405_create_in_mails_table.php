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
        Schema::create('in_mails', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->nullable()->unique();                                                        // identificativo unico della mail
            $table->text('from');                                                                               // mittente
            $table->text('subject');                                                                            // oggetto
            $table->text('body_preview')->nullable();                                                           // anteprima del messaggio
            $table->string('body_path')->nullable();                                                            // percorso file messaggio intero
            $table->date('receive_date');                                                                       // data ricezione mail
            $table->string('attachment_path')->nullable();                                                      // percorso allegati
            $table->foreignId('download_user_id')->nullable()->constrained('users')->onUpdate('cascade');       // id utente che ha scaricato la mail
            $table->timestamps();
        });

        Schema::table('senders', function (Blueprint $table) {
            $table->integer('delete_after_days')->nullable()->after('in_mail_port');                            // giorni dopo i quali cancellare una mail dalla casella
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('in_mails');
        Schema::table('senders', function (Blueprint $table) {
            $table->dropColumn('delete_after_days');
        });
        Schema::enableForeignKeyConstraints();
    }
};
