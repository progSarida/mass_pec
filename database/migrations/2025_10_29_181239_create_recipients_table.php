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
        // Schema::create('recipients', function (Blueprint $table) {                                                  // tabella destinatari
        //     $table->id();
        //     $table->string('description');                                                                          // descrizione
        //     $table->foreignId('admin_type_id')->nullable()->constrained('admin_types')->onUpdate('cascade');        // id tipo amministrazione
        //     $table->foreignId('istat_type_id')->nullable()->constrained('istat_types')->onUpdate('cascade');        // id tipo istat
        //     $table->string('code_ipa');                                                                             // codice Ipa
        //     $table->string('acronym');                                                                              // acronimo
        //     $table->foreignId('city_id')->nullable()->constrained('cities')->onUpdate('cascade');                   // id comune
        //     // $table->string('cc');                                                                                   // codice catastale     ELIMINARE [cc]
        //     // $table->foreignId('province_id')->nullable()->constrained('cities')->onUpdate('cascade');               // id provincia         ELIMINARE [province]
        //     // $table->foreignId('region_id')->nullable()->constrained('cities')->onUpdate('cascade');                 // id regione           ELIMINARE [region]
        //     // $table->string('cap');                                                                                  // cap                  ELIMINARE [cap]
        //     $table->string('address');                                                                              // indirizzo
        //     $table->string('resp_title');                                                                           // titolo responsabile
        //     $table->string('resp_surname');                                                                         // cognome responsabile
        //     $table->string('resp_name');                                                                            // nome responsabile
        //     $table->string('tax_code');                                                                             // codice fiscale
        //     $table->string('mail_1');                                                                               // email 1
        //     $table->string('mail_type_1');                                                                          // tipo email 1 (enum MailType)
        //     $table->string('mail_2');                                                                               // email 2
        //     $table->string('mail_type_2');                                                                          // tipo email 2 (enum MailType)
        //     $table->string('mail_3');                                                                               // email 3
        //     $table->string('mail_type_3');                                                                          // tipo email 3 (enum MailType)
        //     $table->string('mail_4');                                                                               // email 4
        //     $table->string('mail_type_4');                                                                          // tipo email 4 (enum MailType)
        //     $table->string('mail_5');                                                                               // email 5
        //     $table->string('mail_type_5');                                                                          // tipo email 5 (enum MailType)
        //     $table->string('site');                                                                                 // sito
        //     $table->string('url_facebook');                                                                         // account facebook
        //     $table->string('url_twitter');                                                                          // account twitter
        //     $table->string('url_googleplus');                                                                       // account googleplus
        //     $table->string('url_youtube');                                                                          // account youtube
        //     // $table->integer('live_access');                                                                         //                      ELIMINARE [live_access]
        //     $table->timestamps();
        // });
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
