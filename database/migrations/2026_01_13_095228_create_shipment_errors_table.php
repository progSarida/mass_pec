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
        Schema::create('shipment_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onUpdate('cascade')->onDelete('cascade');       // id spedizione
            $table->foreignId('recipient_id')->constrained('recipients')->onUpdate('cascade')->onDelete('cascade');     // id interlocutore
            $table->string('address');                                                                                  // indirizzo interlocutore con errore
            $table->date('send_date');                                                                                  // data invio
            $table->string('shipment_error_type');                                                                      // tipo errore spedizione
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_errors');
    }
};
