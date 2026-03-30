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
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->dateTime('registration_date')->nullable();                                                                          // data registrazione
            $table->string('filename')->nullable();                                                                                     // nome file
            $table->string('from_protocol')->nullable();                                                                                // numero di protocollo di partenza
            $table->string('to_protocol')->nullable();                                                                                  // numero di protocollo di fine
            $table->string('preservation_state')->nullable();                                                                           // stato conservazione (enum PreservationState)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
    }
};
