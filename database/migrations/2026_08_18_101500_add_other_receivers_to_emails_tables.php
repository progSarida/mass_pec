<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('download_emails', function (Blueprint $table) {
            $table->json('other_receivers')->nullable()->after('from');      // altri destinatari certificati (daticert.xml), esclusa la casella ricevente
        });

        Schema::table('registries', function (Blueprint $table) {
            $table->json('other_receivers')->nullable()->after('from');      // altri destinatari certificati (daticert.xml), esclusa la casella ricevente
        });
    }

    public function down(): void
    {
        Schema::table('download_emails', function (Blueprint $table) {
            $table->dropColumn('other_receivers');
        });

        Schema::table('registries', function (Blueprint $table) {
            $table->dropColumn('other_receivers');
        });
    }
};
