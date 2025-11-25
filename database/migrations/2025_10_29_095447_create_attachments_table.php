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
        Schema::create('attachments', function (Blueprint $table) {                                         // tabell aallegati
            $table->id();
            $table->string('name')->nullable();                                                             // nome file
            $table->string('path')->nullable();                                                             // percorso file
            $table->string('extension')->nullable();                                                        // tipo file
            $table->date('upload_date')->nullable();                                                        // data caricamento file
            $table->foreignId('upload_user_id')->nullable()->constrained('users')->onUpdate('cascade');     // id utente che ha caricato file
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('attachments');
        Schema::enableForeignKeyConstraints();
    }
};
