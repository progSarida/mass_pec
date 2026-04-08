<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            $table->json('interested_parties')->nullable()->after('scope_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            $table->dropColumn('interested_parties');
        });
    }
};
