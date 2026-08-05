<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('registry_manages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registry_id')->nullable()->constrained('registries')->onUpdate('cascade');                   // id registro
            $table->string('manage_registry_type')->nullable();                                                             // tipo gestione (enum ManageRegistryType)
            $table->date('manage_registry_date')->nullable();                                                               // data gestione
            $table->text('manage_registry_mode')->nullable();                                                               // modalità evasione
            $table->dateTime('manage_registration_datetime')->nullable();                                                   // data gestione                
            $table->foreignId('manage_registration_user_id')->nullable()->constrained('users')->onUpdate('cascade');        // id settore interno mail
                
            $table->timestamps();
        });

        // Popola la nuova tabella con i dati esistenti di registries
        DB::statement("
            INSERT INTO registry_manages (
                registry_id,
                manage_registry_type,
                manage_registry_date,
                manage_registration_datetime,
                manage_registration_user_id,
                created_at,
                updated_at
            )
            SELECT 
                id AS registry_id,
                manage_registry_type,
                CASE 
                    WHEN manage_registry_type = 'todo' THEN NULL
                    ELSE manage_registry_date
                END AS manage_registry_date,
                created_at AS manage_registration_datetime,
                register_user_id AS manage_registration_user_id,
                NOW(),
                NOW()
            FROM registries
            WHERE manage_registry_type IS NOT NULL 
              AND manage_registry_type != 'none'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registry_manages');
    }
};
