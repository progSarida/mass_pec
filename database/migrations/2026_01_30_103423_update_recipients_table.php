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
        Schema::table('recipients', function (Blueprint $table) {
            $table->string('code_ipa')->nullable()->change();                                                       // codice Ipa
            $table->string('address')->nullable()->change();                                                        // indirizzo
            $table->string('resp_title')->nullable()->change();                                                     // titolo responsabile
            $table->string('resp_surname')->nullable()->change();                                                   // cognome responsabile
            $table->string('resp_name')->nullable()->change();                                                      // nome responsabile
            $table->string('resp_tax_code')->nullable()->change();                                                  // codice fiscale responsabile
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
