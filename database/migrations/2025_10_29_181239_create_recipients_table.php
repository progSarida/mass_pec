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
        Schema::create('recipients', function (Blueprint $table) {                                                  // tabella destinatari
            $table->id();
            $table->string('description');                                                                          // descrizione
            $table->foreignId('admin_type_id')->nullable()->constrained('admin_types')->onUpdate('cascade');        // id tipo amministrazione
            $table->foreignId('istat_type_id')->nullable()->constrained('istat_types')->onUpdate('cascade');        // id tipo istat
            $table->string('code_ipa');                                                                             // codice Ipa
            $table->string('acronym')->nullable();                                                                  // acronimo
            $table->foreignId('city_id')->nullable()->constrained('cities')->onUpdate('cascade');                   // id comune
            // $table->string('cc');                                                                                   // codice catastale     ELIMINARE [cc]
            // $table->foreignId('province_id')->nullable()->constrained('cities')->onUpdate('cascade');               // id provincia         ELIMINARE [province]
            // $table->foreignId('region_id')->nullable()->constrained('cities')->onUpdate('cascade');                 // id regione           ELIMINARE [region]
            // $table->string('cap');                                                                                  // cap                  ELIMINARE [cap]
            $table->string('address');                                                                              // indirizzo
            $table->string('resp_title');                                                                           // titolo responsabile
            $table->string('resp_surname');                                                                         // cognome responsabile
            $table->string('resp_name');                                                                            // nome responsabile
            $table->string('resp_tax_code');                                                                         // codice fiscale responsabile
            $table->string('mail_1')->nullable();                                                                   // email 1
            $table->string('mail_type_1')->nullable();                                                              // tipo email 1 (enum MailType)
            $table->string('mail_2')->nullable();                                                                   // email 2
            $table->string('mail_type_2')->nullable();                                                              // tipo email 2 (enum MailType)
            $table->string('mail_3')->nullable();                                                                   // email 3
            $table->string('mail_type_3')->nullable();                                                              // tipo email 3 (enum MailType)
            $table->string('mail_4')->nullable();                                                                   // email 4
            $table->string('mail_type_4')->nullable();                                                              // tipo email 4 (enum MailType)
            $table->string('mail_5')->nullable();                                                                   // email 5
            $table->string('mail_type_5')->nullable();                                                              // tipo email 5 (enum MailType)
            $table->string('site')->nullable();                                                                     // sito
            $table->string('url_facebook')->nullable();                                                             // account facebook
            $table->string('url_twitter')->nullable();                                                              // account twitter
            $table->string('url_googleplus')->nullable();                                                           // account googleplus
            $table->string('url_youtube')->nullable();                                                              // account youtube
            // $table->integer('live_access');                                                                         //                      ELIMINARE [live_access]
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('recipients');
        Schema::enableForeignKeyConstraints();
    }
};
