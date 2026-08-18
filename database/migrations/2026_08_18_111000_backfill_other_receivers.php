<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Recupera gli altri destinatari delle PEC già scaricate rileggendo i daticert.xml
 * conservati tra gli allegati.
 *
 * Il parsing è ripetuto qui invece di richiamare DownloadEmailsJob: una migrazione
 * è un fatto storico e deve restare valida anche se quel codice viene rifattorizzato.
 * Per lo stesso motivo si lavora con DB::table() e non con i model, così cast,
 * eventi e timestamp non possono cambiarne il comportamento nel tempo.
 */
return new class extends Migration
{
    /** Tabelle da recuperare: entrambe hanno attachment_path e receiving_mail */
    private const TABLES = ['download_emails', 'registries'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->backfill($table);
        }
    }

    /**
     * Non reversibile: i valori scritti qui sono indistinguibili da quelli che il
     * download delle PEC scrive normalmente, quindi azzerarli cancellerebbe anche
     * i dati validi. L'elenco delle voci toccate resta nel log, se serve rimediare.
     */
    public function down(): void
    {
        //
    }

    private function backfill(string $table): void
    {
        $storage = Storage::disk(config('filesystems.default'));

        $esaminati = 0;
        $aggiornati = 0;
        $senzaDaticert = 0;
        $soloRicevente = 0;
        $illeggibili = 0;

        // whereNull: la migrazione non sovrascrive mai un valore già presente
        DB::table($table)
            ->whereNull('other_receivers')
            ->whereNotNull('attachment_path')
            ->whereNotNull('receiving_mail')
            ->orderBy('id')
            ->chunkById(200, function ($records) use (
                $table, $storage, &$esaminati, &$aggiornati, &$senzaDaticert, &$soloRicevente, &$illeggibili
            ) {
                foreach ($records as $record) {
                    $esaminati++;

                    // Il nome del file dipende da quando la voce è stata protocollata: gli allegati
                    // vengono rinominati con un prefisso che nel tempo è cambiato, quindi si cerca
                    // per suffisso invece che per percorso esatto.
                    $directory = rtrim($record->attachment_path, '/') . '/tech';

                    try {
                        $path = collect($storage->files($directory))
                            ->first(fn ($file) => str_ends_with(mb_strtolower(basename($file)), 'daticert.xml'));

                        if ($path === null) {
                            $senzaDaticert++;                   // voce non originata da una busta PEC
                            continue;
                        }

                        $xml = $storage->get($path);
                    } catch (\Throwable $e) {
                        // Un dato accessorio non deve poter bloccare il deploy
                        $illeggibili++;
                        Log::warning("[backfill other_receivers] {$table} #{$record->id}: lettura da {$directory} fallita ({$e->getMessage()})");
                        continue;
                    }

                    $receivers = $this->otherReceivers($xml, $record->receiving_mail, "{$table} #{$record->id}");

                    if ($receivers === []) {
                        $soloRicevente++;
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $record->id)
                        ->update(['other_receivers' => json_encode($receivers)]);   // updated_at volutamente non toccato

                    $aggiornati++;

                    // Tracciato di ciò che è stato scritto: è l'unico appiglio in caso di rimedio manuale
                    Log::info("[backfill other_receivers] {$table} #{$record->id} ({$record->receiving_mail}) -> " . implode(' | ', $receivers));
                }
            });

        Log::info(
            "[backfill other_receivers] {$table}: esaminati {$esaminati}, aggiornati {$aggiornati},"
            . " senza daticert {$senzaDaticert}, solo casella ricevente {$soloRicevente}, illeggibili {$illeggibili}"
        );
    }

    /**
     * Destinatari certificati presenti nel daticert.xml, esclusa la casella ricevente.
     */
    private function otherReceivers(?string $xml, ?string $ownAddress, string $context): array
    {
        if (blank($xml)) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $postacert = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($postacert === false) {
            Log::warning("[backfill other_receivers] {$context}: daticert.xml non interpretabile");

            return [];
        }

        $own = mb_strtolower(trim((string) $ownAddress));
        $receivers = [];

        foreach ($postacert->intestazione->destinatari ?? [] as $destinatario) {
            $email = mb_strtolower(trim((string) $destinatario));

            if ($email === '' || $email === $own) {
                continue;
            }

            $receivers[$email] = $email;        // chiave = email per deduplicare
        }

        return array_values($receivers);
    }
};
