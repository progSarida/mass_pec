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
            $table->foreignId('signature_id')->nullable()->after('account_id')->constrained('signatures')->onUpdate('cascade');         // id firma messaggio
            $table->string('mail_type')->nullable()->after('signature_id');                                                             // tipo mail
            $table->foreignId('office_type_id')->nullable()->after('mail_type')->constrained('office_types')->onUpdate('cascade');      // id tipo ufficio
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
