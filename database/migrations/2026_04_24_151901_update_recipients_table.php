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
            $table->string('recipient_type')->nullable()->after('description');                                 // natura interlocutore
            $table->string('tax_code')->nullable()->after('istat_type_id');                                     // codice fiscale
            $table->string('vat_code')->nullable()->after('tax_code');                                          // partita IVA
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
