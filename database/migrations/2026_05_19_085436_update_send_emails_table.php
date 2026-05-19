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
        Schema::table('send_emails', function (Blueprint $table) {
            $table->boolean('is_reply')->nullable()->after('create_user_id');                                           // flag risposta
            $table->text('is_forward')->nullable()->after('is_reply');                                                  // flag inoltro
            $table->integer('linked_registry_id')->nullable()->after('is_forward');                                     // id voce protocollo collegata
        });

        Schema::table('manual_inserts', function (Blueprint $table) {
            $table->boolean('is_reply')->nullable()->after('create_user_id');                                           // flag risposta
            $table->text('is_forward')->nullable()->after('is_reply');                                                  // flag inoltro
            $table->integer('linked_registry_id')->nullable()->after('is_forward');                                     // id voce protocollo collegata
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('send_emails', function (Blueprint $table) {
            $table->dropColumn(['is_reply', 'is_forward', 'linked_registry_id']);
        });
        Schema::table('manual_inserts', function (Blueprint $table) {
            $table->dropColumn(['is_reply', 'is_forward', 'linked_registry_id']);
        });
    }
};
