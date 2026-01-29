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
        Schema::table('download_emails', function (Blueprint $colonna) {
            $colonna->dateTime('receive_date')->nullable()->change();                                                       // trasformo il campo in datetime
        });

        Schema::table('in_mails', function (Blueprint $colonna) {
            $colonna->dateTime('receive_date')->nullable()->change();                                                       // trasformo il campo in datetime
        });

        Schema::table('registries', function (Blueprint $colonna) {
            $colonna->dateTime('receive_date')->nullable()->change();                                                       // trasformo il campo in datetime
            $colonna->dateTime('send_date')->nullable()->change();                                                          // trasformo il campo in datetime
        });

        Schema::table('shipments', function (Blueprint $colonna) {
            $colonna->dateTime('send_date')->nullable()->change();                                                          // trasformo il campo in datetime
        });

        Schema::table('receivers', function (Blueprint $colonna) {
            $colonna->dateTime('send_date')->nullable()->change();                                                          // trasformo il campo in datetime
        });

        Schema::table('send_emails', function (Blueprint $colonna) {
            $colonna->dateTime('send_date')->nullable()->change();                                                          // trasformo il campo in datetime
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::table('download_emails', function (Blueprint $colonna) {
        //     $colonna->date('receive_date')->change();
        // });

        // Schema::table('in_mails', function (Blueprint $colonna) {
        //     $colonna->date('receive_date')->change();
        // });

        // Schema::table('registries', function (Blueprint $colonna) {
        //     $colonna->date('receive_date')->change();
        //     $colonna->date('send_date')->change();
        // });

        // Schema::table('shipments', function (Blueprint $colonna) {
        //     $colonna->date('send_date')->change();
        // });

        // Schema::table('receivers', function (Blueprint $colonna) {
        //     $colonna->date('send_date')->change();
        // });

        // Schema::table('send_emails', function (Blueprint $colonna) {
        //     $colonna->date('send_date')->change();
        // });
    }
};
