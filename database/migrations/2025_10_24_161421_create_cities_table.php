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
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->char('alpha2', 2);
            $table->char('alpha3', 3)->nullable();
            $table->smallInteger('country_code')->nullable();
            $table->string('iso_3166_2', 20)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('sub_region', 50)->nullable();
            $table->string('intermediate_region', 50)->nullable();
            $table->smallInteger('region_code')->nullable();
            $table->smallInteger('sub_region_code')->nullable();
            $table->smallInteger('intermediate_region_code')->nullable();
            $table->unique('alpha2', 'uk_alpha2');
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions');
            $table->string('name');
            $table->string('code');
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces');
            $table->string('name');
            $table->string('code',4)->unique();
            $table->string('zip_code');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('states');
        Schema::enableForeignKeyConstraints();
    }
};
