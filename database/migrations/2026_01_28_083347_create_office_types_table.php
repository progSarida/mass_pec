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
        Schema::create('office_types', function (Blueprint $table) {                                                    // tabella tipi uffici
            $table->id();
            $table->string('name');                                                                                     // tipo ufficio
            $table->integer('position');                                                                                // posizione il selezione
            $table->timestamps();
        });

        Schema::table('recipients', function (Blueprint $table) {                                                       //
            $table->foreignId('office_type_id_1')->after('mail_type_1')->nullable()->constrained('office_types');       // id account mittente
            $table->foreignId('office_type_id_2')->after('mail_type_2')->nullable()->constrained('office_types');       // id account mittente
            $table->foreignId('office_type_id_3')->after('mail_type_3')->nullable()->constrained('office_types');       // id account mittente
            $table->foreignId('office_type_id_4')->after('mail_type_4')->nullable()->constrained('office_types');       // id account mittente
            $table->foreignId('office_type_id_5')->after('mail_type_5')->nullable()->constrained('office_types');       // id account mittente
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
