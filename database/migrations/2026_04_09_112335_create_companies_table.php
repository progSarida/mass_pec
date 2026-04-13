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
        Schema::create('companies', function (Blueprint $table) {                                                                   // tabella dati gestore
            $table->id();
            $table->string('name')->nullable();                                                                                     // flusso voce protocollo
            $table->string('vat_number')->nullable();                                                                               // partita iva
            $table->string('tax_number')->nullable();                                                                               // codice fiscale
            $table->foreignId('state_id')->nullable()->references('id')->on('states')->nullOnDelete();                              // id paese
            $table->string('address')->nullable();                                                                                  // indirizzo
            $table->string('city_code',4)->nullable();                                                                              // codice catastale
            $table->foreignId('city_id')->nullable()->references('id')->on('cities');
            $table->string('place')->nullable();                                                                                    // luogo generico non in italia
            $table->string('phone')->nullable();                                                                                    // telefono
            $table->string('email')->nullable();                                                                                    // email
            $table->string('pec')->nullable();                                                                                      // pec
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
