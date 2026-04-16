<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ZBateson\MailMimeParser\MailMimeParser;
use App\Models\DownloadEmail;
use App\Models\Registry;
use App\Enums\RegistryOriginType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Aggiungo le colonne
        Schema::table('download_emails', function (Blueprint $table) {
            $table->text('eml_body')->nullable()->after('body');                                                        // contenuto file eml
        });

        Schema::table('in_mails', function (Blueprint $table) {
            $table->text('eml_body')->nullable()->after('body');                                                        // contenuto file eml
        });

        Schema::table('registries', function (Blueprint $table) {
            $table->text('eml_body')->nullable()->after('body');                                                        // contenuto file eml
        });

        // 2. Popolo i dati per download_emails
        $this->updateDownloadEmails();

        // 3. Popolo i dati per registries
        $this->updateRegistries();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            $table->dropColumn('eml_body');
        });

        Schema::table('in_mails', function (Blueprint $table) {
            $table->dropColumn('eml_body');
        });

        Schema::table('download_emails', function (Blueprint $table) {
            $table->dropColumn('eml_body');
        });
    }

    /**
     * Aggiorno il corpo delle email scaricate usando il file eml associato, se presente.
     */
    private function updateDownloadEmails(): void
    {
        Log::info("Inizio aggiornamento corpo download_emails");

        $parser = new MailMimeParser();
        $disk = config('filesystems.default');
        $storage = Storage::disk($disk);

        DownloadEmail::whereNull('eml_body')
            ->chunkById(100, function ($emails) use ($parser, $storage) {
                foreach ($emails as $email) {
                    $emlFile = collect($storage->files("download_email/{$email->id}"))
                        ->filter(fn($path) => pathinfo($path, PATHINFO_EXTENSION) === 'eml')
                        ->first();

                    if (!$emlFile) {
                        Log::warning("Nessun file .eml trovato per email {$email->id}");
                        continue;
                    }

                    Log::info($emlFile);

                    $content = $storage->get($emlFile);
                    $message = $parser->parse($content, false);

                    $testo_semplice = $message->getTextContent();

                    if (empty($testo_semplice)) {
                        $html = $message->getHtmlContent();
                        if (!empty($html)) {
                            $testo_semplice = $html;
                        }
                    }

                    $email->update([
                        'eml_body' => substr($this->sanitizeUtf8($testo_semplice), 0, 10000)
                    ]);

                    Log::info("Email {$email->id} aggiornata");
                }
            });
    }

    /**
     * Aggiorno il corpo delle voci delò protocollo che hanno origine da email scaricate usando il file eml associato, se presente.
     */
    private function updateRegistries(): void
    {
        Log::info("Inizio aggiornamento corpo registries");

        $parser = new MailMimeParser();
        $disk = config('filesystems.default');
        $storage = Storage::disk($disk);

        Registry::whereIn('registry_origin_type', [
                RegistryOriginType::DOWNLOAD_EMAIL,
                RegistryOriginType::IN_MAIL
            ])
            ->whereNull('eml_body')
            ->chunkById(100, function ($registries) use ($parser, $storage) {
                foreach ($registries as $registry) {
                    $emlFile = collect($storage->files("registry/{$registry->protocol_number}/tech"))
                        ->filter(fn($path) => pathinfo($path, PATHINFO_EXTENSION) === 'eml')
                        ->first();

                    if (!$emlFile) {
                        Log::warning("Nessun file .eml trovato per protocollo {$registry->protocol_number} (ID: {$registry->id})");
                        continue;
                    }

                    Log::info($emlFile);

                    $content = $storage->get($emlFile);
                    $message = $parser->parse($content, false);

                    $testo_semplice = $message->getTextContent();

                    if (empty($testo_semplice)) {
                        $html = $message->getHtmlContent();
                        if (!empty($html)) {
                            $testo_semplice = $html;
                        }
                    }

                    $registry->update([
                        'eml_body' => substr($this->sanitizeUtf8($testo_semplice), 0, 10000)
                    ]);

                    Log::info("Registry {$registry->id} aggiornata");
                }
            });
    }

    /**
     * Sanitizzo l'encoding UTF-8
     */
    private function sanitizeUtf8(?string $text): string
    {
        if (is_null($text) || $text === '') {
            return '';
        }

        // 1. Forza la conversione in UTF-8 per gestire eventuali byte malformati
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // 2. Elimina i caratteri che rimangono "illegali" per il database
        // Il flag //IGNORE pulisce la stringa senza interrompere l'esecuzione
        $sanitized = iconv('UTF-8', 'UTF-8//IGNORE', $text);

        return is_string($sanitized) ? $sanitized : '';
    }
};
