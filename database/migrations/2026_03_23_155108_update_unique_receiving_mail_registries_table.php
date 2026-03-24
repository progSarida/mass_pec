<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('download_emails', function (Blueprint $table) {
            // 1. Inserisce il nuovo campo dopo id
            $table->string('receiving_mail')->nullable()->after('id');
        });

        Schema::table('in_mails', function (Blueprint $table) {
            // 1. Inserisce il nuovo campo dopo id
            $table->string('receiving_mail')->nullable()->after('id');
        });

        Schema::table('registries', function (Blueprint $table) {
            // 1. Inserisce il nuovo campo dopo registry_origin_type
            $table->string('receiving_mail')->nullable()->after('registry_origin_type');

            // 2. Rimuove il vecchio vincolo unique
            $table->dropUnique(['message_id', 'registry_origin_type']);
        });

        // 3. Popolamento dati esistenti
        DB::table('registries')->where('registry_origin_type', 'download_email')
            ->update(['receiving_mail' => 'protocollo@pec.sarida.it']);

        DB::table('registries')->where('registry_origin_type', 'in_mail')
            ->update(['receiving_mail' => 'corrispondenza@pec.sarida.it']);

        Schema::table('registries', function (Blueprint $table) {
            // 4. Creazione del nuovo vincolo unique
            $table->unique(['message_id', 'receiving_mail']);
        });
    }

    public function down()
    {
        Schema::table('registries', function (Blueprint $table) {
            $table->dropUnique('registries_message_id_receiving_mail_unique');
            $table->unique(['message_id', 'registry_origin_type'], 'registries_message_id_registry_origin_type_unique');
            $table->dropColumn('receiving_mail');
        });

        Schema::table('in_mails', function (Blueprint $table) {
            $table->dropColumn('receiving_mail');
        });

        Schema::table('registries', function (Blueprint $table) {
            $table->download_emails('receiving_mail');
        });
    }
};
