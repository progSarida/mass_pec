<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('registry_relationships', function (Blueprint $table) {
        $table->id();
        $table->foreignId('parent_id')->constrained('registries')->cascadeOnDelete();
        $table->foreignId('child_id')->constrained('registries')->cascadeOnDelete();
        $table->string('relationship_type');
        $table->timestamps();

        $table->unique(['parent_id', 'child_id', 'relationship_type'], 'registry_rel_unique'); // evita duplicati
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registry_relationships');
    }
};
