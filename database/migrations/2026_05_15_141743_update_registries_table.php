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
            $table->boolean('void')->nullable()->after('manage_registry_date');                                     // flag annullamento voce
            $table->text('void_reason')->nullable()->after('void');                                                 // motivo annullamento voce
            $table->date('void_date')->nullable()->after('void_reason');                                            // data annullamento
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
