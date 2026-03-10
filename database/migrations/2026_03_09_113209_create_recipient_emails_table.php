<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipient_emails', function (Blueprint $table) {                             // tabella email interlocutori
            $table->id();
            $table->foreignId('recipient_id')->constrained()->onDelete('cascade');                          // id interlocutore
            $table->string('email');                                                                                // indirizzo
            $table->string('mail_type')->nullable();                                                                // tipo email
            $table->foreignId('office_type_id')->nullable()->constrained('office_types');                    // id ufficio
            $table->integer('order')->default(0);                                                            // ordinamento
            $table->timestamps();

            $table->index(['recipient_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipient_emails');
    }
};
