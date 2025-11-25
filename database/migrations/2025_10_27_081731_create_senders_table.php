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
        Schema::create('senders', function (Blueprint $table) {
            $table->id();
            $table->string('cc', 5)->nullable();
            $table->string('management_type', 50);
            $table->string('mail_type', 50);
            $table->string('address', 100);
            $table->string('username', 100);
            $table->string('password', 250);
            $table->string('public_name', 100);
            $table->string('connection_safety_type', 30);
            $table->string('in_mail_server', 100);
            $table->string('in_mail_protocol_type', 10);
            $table->string('in_mail_port', 10);
            $table->string('out_mail_server', 100);
            $table->string('out_mail_protocol_type', 10);
            $table->string('out_mail_port', 10);
            $table->string('out_authentication', 5);
            $table->string('out_username', 100);
            $table->string('out_password', 250);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('senders');
        Schema::enableForeignKeyConstraints();
    }
};
