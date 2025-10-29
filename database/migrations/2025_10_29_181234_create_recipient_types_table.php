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
        // Schema::create('istat_types', function (Blueprint $table) {                                                 // tabella tipi istat
        //     $table->id();
        //     $table->string('name');                                                                                 // tipo istat
        //     $table->integer('position');                                                                            // posizione il selezione
        //     $table->timestamps();
        // });

        // Schema::create('admin_types', function (Blueprint $table) {                                                 // tabella tipi ammministrazioni
        //     $table->id();
        //     $table->string('name');                                                                                 // tipo amministrazione
        //     $table->integer('position');                                                                            // posizione il selezione
        //     $table->timestamps();       
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('admin_types');
        Schema::dropIfExists('istat_types');
        Schema::enableForeignKeyConstraints();
    }
};
