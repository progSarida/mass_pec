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
        Schema::create('pec_interactions', function (Blueprint $table) {
            $table->id();
            $table->string('pec_interaction_type')->nullable();                                                                     // tipo di interazione PEC
            $table->foreignId('registry_id')->nullable()->constrained('registries')->onUpdate('cascade');                          // id registro associato
            $table->date('interaction_date')->nullable();                                                                           // data interazione
            $table->foreignId('user_id')->nullable()->constrained('users')->onUpdate('cascade');                                    // id utente che ha avuto l'interazione
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pec_interactions');
    }
};
