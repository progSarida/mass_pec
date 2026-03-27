<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Array di configurazione per gestire la rimozione dei CASCADE.
     * Imposta 'remove_cascade' a true per rimuovere il CASCADE, false per mantenerlo.
     *
     */
    private array $foreignKeys = [
        [
            'table' => 'account_user',
            'column' => 'account_id',
            'references' => 'id',
            'on' => 'accounts',
            'remove_cascade' => true, // Cambia a true per rimuovere CASCADE
        ],
        [
            'table' => 'account_user',
            'column' => 'user_id',
            'references' => 'id',
            'on' => 'users',
            'remove_cascade' => true, // Cambia a true per rimuovere CASCADE
        ],
        [
            'table' => 'receivers',
            'column' => 'shipment_id',
            'references' => 'id',
            'on' => 'shipments',
            'remove_cascade' => true, // Cambia a true per rimuovere CASCADE
        ],
        [
            'table' => 'recipient_emails',
            'column' => 'recipient_id',
            'references' => 'id',
            'on' => 'recipients',
            'remove_cascade' => true, // Cambia a true per rimuovere CASCADE
        ],
        [
            'table' => 'registry_receivers',
            'column' => 'registry_id',
            'references' => 'id',
            'on' => 'registries',
            'remove_cascade' => true, // Cambia a true per rimuovere CASCADE
        ],
        [
            'table' => 'shipment_errors',
            'column' => 'recipient_id',
            'references' => 'id',
            'on' => 'recipients',
            'remove_cascade' => true, // Cambia a true per rimuovere CASCADE
        ],
        [
            'table' => 'shipment_errors',
            'column' => 'shipment_id',
            'references' => 'id',
            'on' => 'shipments',
            'remove_cascade' => true, // Cambia a true per rimuovere CASCADE
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->foreignKeys as $fk) {
            if ($fk['remove_cascade']) {
                $this->removeCascadeFromForeignKey(
                    $fk['table'],
                    $fk['column'],
                    $fk['references'],
                    $fk['on']
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->foreignKeys as $fk) {
            if ($fk['remove_cascade']) {
                $this->addCascadeToForeignKey(
                    $fk['table'],
                    $fk['column'],
                    $fk['references'],
                    $fk['on']
                );
            }
        }
    }

    /**
     * Rimuove il CASCADE da una foreign key esistente
     */
    private function removeCascadeFromForeignKey(string $table, string $column, string $references, string $on): void
    {
        // Verifica se il constraint esiste
        $constraintName = $this->getForeignKeyConstraintName($table, $column);

        if (!$constraintName) {
            echo "⚠️  Foreign key constraint per {$table}.{$column} non trovato, skip...\n";
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $references, $on, $constraintName) {
            // Rimuove il constraint esistente usando il nome effettivo
            $blueprint->dropForeign($constraintName);

            // Ricrea il constraint SENZA onDelete('cascade')
            $blueprint->foreign($column)
                ->references($references)
                ->on($on)
                ->onUpdate('cascade'); // Manteniamo l'onUpdate cascade
        });

        echo "✅  Rimosso CASCADE da {$table}.{$column}\n";
    }

    /**
     * Aggiunge nuovamente il CASCADE ad una foreign key (per il rollback)
     */
    private function addCascadeToForeignKey(string $table, string $column, string $references, string $on): void
    {
        // Verifica se il constraint esiste
        $constraintName = $this->getForeignKeyConstraintName($table, $column);

        if (!$constraintName) {
            echo "⚠️  Foreign key constraint per {$table}.{$column} non trovato durante il rollback, skip...\n";
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $references, $on, $constraintName) {
            // Rimuove il constraint senza cascade
            $blueprint->dropForeign($constraintName);

            // Ricrea il constraint CON onDelete('cascade')
            $blueprint->foreign($column)
                ->references($references)
                ->on($on)
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        echo "✅  Ripristinato CASCADE su {$table}.{$column}\n";
    }

    /**
     * Ottiene il nome effettivo del constraint della foreign key
     */
    private function getForeignKeyConstraintName(string $table, string $column): ?string
    {
        $databaseName = config('database.connections.mysql.database');

        $constraint = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ", [$databaseName, $table, $column]);

        return $constraint ? $constraint->CONSTRAINT_NAME : null;
    }
};
