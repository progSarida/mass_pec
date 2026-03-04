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
        Schema::table('download_emails', function (Blueprint $table) {
            $table->foreignId('sender_id')->nullable()->after('message_id')->constrained('recipients');        // id mittente
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('download_emails', function (Blueprint $table) {
            //
        });
    }
};
