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
        Schema::table('manual_inserts', function (Blueprint $table) {
            $table->boolean('pending_receipt')->default(false)->after('flow_type');                                  // flag applicazione watermark protcollo a pdf allegati
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manual_inserts', function (Blueprint $table) {
            $table->dropColumn(['pending_receipt']);
        });
    }
};
