<?php

namespace App\Jobs;

use App\Models\Recipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class FixRecipientDescriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    protected $userId;
    protected $currentRecipientId; // Traccia l'ID corrente

    public function __construct($userId = null)
    {
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $processedCount = 0;
        $modifiedCount = 0;

        Recipient::chunk(100, function ($recipients) use (&$processedCount, &$modifiedCount) {
            foreach ($recipients as $recipient) {
                $this->currentRecipientId = $recipient->id;

                try {
                    $pattern = '/\b([a-z]*?)([aeiou])\'(?=\s|$|[^\w])/ui';
                    Log::info("Id interlocutore: {$recipient->id} ----------------------------------------------------------------");
                    $originalDescription = $recipient->description;

                    $newDescription = preg_replace_callback($pattern, function ($matches) {
                        $prefisso = mb_strtolower($matches[1]);
                        $vocaleOriginale = $matches[2];
                        $parolaCompleta = $prefisso . mb_strtolower($vocaleOriginale);

                        if (in_array($parolaCompleta, ['de', 'ca'])) {
                            return $matches[0];
                        }

                        $vocaleLower = mb_strtolower($vocaleOriginale);
                        $mappa = [
                            'a' => 'à', 'e' => 'è', 'i' => 'ì', 'o' => 'ò', 'u' => 'ù'
                        ];
                        $sostituta = $mappa[$vocaleLower] ?? $vocaleOriginale;

                        if (mb_strtoupper($vocaleOriginale) === $vocaleOriginale && $vocaleOriginale !== $vocaleLower) {
                            $sostituta = mb_strtoupper($sostituta);
                        }

                        return $matches[1] . $sostituta;
                    }, $recipient->description);

                    $processedCount++;

                    if ($originalDescription !== $newDescription) {
                        Log::info("Id interlocutore: {$recipient->id} - MODIFICATO");
                        Log::info("Prima: {$originalDescription}");
                        Log::info("Dopo: {$newDescription}");
                        $modifiedCount++;
                    }

                    // SEMPRE save() - il hook saving rigenererà description_search
                    $recipient->description = $newDescription;
                    $recipient->save();

                } catch (\Throwable $e) {
                    Log::error("Errore processando Recipient ID {$recipient->id}: " . $e->getMessage(), [
                        'recipient_id' => $recipient->id,
                        'description' => $recipient->description,
                        'exception' => $e,
                    ]);
                }
            }
        });

        Log::info("Operazione completata. Processati: {$processedCount}, Modificati: {$modifiedCount}");

        if ($this->userId) {
            Notification::make()
                ->title('Operazione completata')
                ->body("Processati {$processedCount} record, modificati {$modifiedCount}.")
                ->success()
                ->sendToDatabase(\App\Models\User::find($this->userId));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('FixRecipientDescriptionsJob fallito: ' . $exception->getMessage(), [
            'last_processed_recipient_id' => $this->currentRecipientId,
            'exception_class' => get_class($exception),
            'trace' => $exception->getTraceAsString()
        ]);

        if ($this->userId) {
            $message = $this->currentRecipientId
                ? "Errore al Recipient ID: {$this->currentRecipientId}. {$exception->getMessage()}"
                : "Si è verificato un errore durante la pulizia delle descrizioni.";

            Notification::make()
                ->title('Operazione fallita')
                ->body($message)
                ->danger()
                ->sendToDatabase(\App\Models\User::find($this->userId));
        }
    }
}
