<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Aggiungo la colonna
        Schema::table('recipients', function (Blueprint $table) {
            $table->string('description_search')->nullable()->after('description');
        });

        // 2. Popolo la colonna per i record esistenti
        // Uso i chunk per non saturare la memoria con 20.000 record
        DB::table('recipients')->orderBy('id')->chunkById(500, function ($recipients) {
            foreach ($recipients as $recipient) {
                $normalized = Str::of($recipient->description)
                    ->trim()
                    ->squish()
                    ->lower()
                    ->toString();

                DB::table('recipients')
                    ->where('id', $recipient->id)
                    ->update(['description_search' => $normalized]);
            }
        });

        // 3. Aggiungo unique dopo aver popolato i dati
        Schema::table('recipients', function (Blueprint $table) {
            $table->unique('description_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->dropColumn('description_search');
        });
    }
};
