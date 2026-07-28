<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipient_emails', function (Blueprint $table) {
            $table->dropForeign('recipient_emails_recipient_id_foreign');

            $table->foreign('recipient_id')
                ->references('id')->on('recipients')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('recipient_emails', function (Blueprint $table) {
            $table->dropForeign('recipient_emails_recipient_id_foreign');

            $table->foreign('recipient_id')
                ->references('id')->on('recipients')
                ->onUpdate('cascade');
        });
    }
};