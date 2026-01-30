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
        Schema::table('registries', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('receive_date')->constrained('accounts');    // id account mittente
            $table->json('recipients')->nullable()->after('account_id');                                    // destinatari
            $table->dropForeign(['send_email_id']);
            $table->dropColumn(['send_email_id']);                                                            // rimuovo la colonna
        });

        Schema::table('send_emails', function (Blueprint $table) {
            $table->dropForeign(['send_user_id']);
            $table->dropColumn(['send_date', 'send_user_id']);                                              // rimuovo le colonne
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
