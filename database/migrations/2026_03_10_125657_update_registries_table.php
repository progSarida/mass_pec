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
            $table->string('manage_registry_type')->nullable()->after('register_user_id');              // json con id altri mittenti
            $table->date('manage_registry_date')->nullable()->after('manage_registry_type');                     // data gestione
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
